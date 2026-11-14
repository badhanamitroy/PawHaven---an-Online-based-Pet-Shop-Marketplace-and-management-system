<?php

namespace App\Services;

use App\Mail\EmploymentOfferOtpMail;
use App\Mail\PendingEmployeeApprovalMail;
use App\Models\BusinessMembership;
use App\Models\Branch;
use App\Models\Company;
use App\Models\EmploymentOffer;
use App\Models\EmploymentOfferOtpChallenge;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmploymentOfferOtpService
{
    public const EXPIRY_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(private RecruitmentService $recruitment) {}

    public function start(EmploymentOffer $offer, string $rawToken): EmploymentOfferOtpChallenge
    {
        return $this->startForOffer($offer, $this->recruitment->tokenHash($rawToken));
    }

    public function resendFromSession(int $offerId, string $tokenHash): EmploymentOfferOtpChallenge
    {
        $offer = EmploymentOffer::query()->findOrFail($offerId);

        return $this->startForOffer($offer, $tokenHash);
    }

    private function startForOffer(EmploymentOffer $offer, string $expectedTokenHash): EmploymentOfferOtpChallenge
    {
        $otp = sprintf('%06d', random_int(100000, 999999));
        $challenge = DB::transaction(function () use ($offer, $expectedTokenHash, $otp): EmploymentOfferOtpChallenge {
            Company::query()->withoutGlobalScopes()->whereKey($offer->company_id)->lockForUpdate()->firstOrFail();
            JobApplication::query()->whereKey($offer->job_application_id)->lockForUpdate()->firstOrFail();
            $lockedOffer = EmploymentOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            Branch::query()->withoutGlobalScopes()->whereKey($lockedOffer->branch_id)->lockForUpdate()->firstOrFail();
            $this->assertOfferValid($lockedOffer, null, $expectedTokenHash);

            $current = EmploymentOfferOtpChallenge::query()->where('active_offer_id', $lockedOffer->id)
                ->lockForUpdate()->first();
            if ($current && $current->last_sent_at?->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture()) {
                throw ValidationException::withMessages(['otp' => 'Please wait before requesting another verification code.']);
            }
            if ($current) {
                $current->active_offer_id = null;
                $current->invalidated_at = now();
                $current->save();
            }

            return EmploymentOfferOtpChallenge::create([
                'offer_id' => $lockedOffer->id,
                'active_offer_id' => $lockedOffer->id,
                'email' => $lockedOffer->candidate_email,
                'otp_hash' => $this->otpHash($lockedOffer, $otp),
                'attempts' => 0,
                'max_attempts' => self::MAX_ATTEMPTS,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
                'last_sent_at' => now(),
            ]);
        });

        try {
            Mail::to($challenge->email)->send(new EmploymentOfferOtpMail($otp, self::EXPIRY_MINUTES));
        } catch (Throwable $exception) {
            $challenge->forceFill(['active_offer_id' => null, 'invalidated_at' => now()])->save();
            Log::warning('Offer email verification delivery failed.', [
                'offer_id' => $offer->id,
                'challenge_id' => $challenge->id,
                'error_type' => get_class($exception),
            ]);
            throw ValidationException::withMessages(['email' => 'We could not send the verification code. Reopen the offer link and try again.']);
        }

        return $challenge;
    }

    /** Verify and create the restricted pending employee atomically. */
    public function verifyAndCreateEmployee(int $challengeId, int $offerId, string $tokenHash, string $code): User
    {
        $initialOffer = EmploymentOffer::query()->findOrFail($offerId);
        if ($initialOffer->status === 'accepted'
            && $initialOffer->employee_user_id !== null
            && hash_equals($initialOffer->token_hash, $tokenHash)) {
            $usedChallenge = EmploymentOfferOtpChallenge::query()->whereKey($challengeId)
                ->where('offer_id', $offerId)->whereNotNull('verified_at')->whereNotNull('consumed_at')->first();
            if ($usedChallenge
                && mb_strtolower(trim($usedChallenge->email)) === mb_strtolower(trim($initialOffer->candidate_email))) {
                return User::query()->findOrFail($initialOffer->employee_user_id);
            }
        }
        $result = DB::transaction(function () use ($challengeId, $offerId, $tokenHash, $code, $initialOffer): array {
            Company::query()->withoutGlobalScopes()->whereKey($initialOffer->company_id)->lockForUpdate()->firstOrFail();
            $application = JobApplication::query()->whereKey($initialOffer->job_application_id)->lockForUpdate()->firstOrFail();
            $offer = EmploymentOffer::query()->whereKey($offerId)->lockForUpdate()->firstOrFail();
            Branch::query()->withoutGlobalScopes()->whereKey($offer->branch_id)->lockForUpdate()->firstOrFail();
            $challenge = EmploymentOfferOtpChallenge::query()->whereKey($challengeId)
                ->where('offer_id', $offerId)->lockForUpdate()->firstOrFail();
            $this->assertOfferValid($offer, null, $tokenHash);

            if ($challenge->active_offer_id !== $offer->id || $challenge->invalidated_at !== null || $challenge->consumed_at !== null) {
                return ['error' => 'This verification code is no longer valid. Reopen the offer link to request another.'];
            }
            if ($challenge->expires_at->isPast()) {
                $this->invalidate($challenge);

                return ['error' => 'The verification code expired. Reopen the offer link to request another.'];
            }
            if ($challenge->attempts >= $challenge->max_attempts) {
                $this->invalidate($challenge);

                return ['error' => 'The maximum number of attempts was reached. Reopen the offer link to request another.'];
            }
            if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1 || ! hash_equals($challenge->otp_hash, $this->otpHash($offer, $code))) {
                $challenge->attempts++;
                if ($challenge->attempts >= $challenge->max_attempts) {
                    $this->invalidate($challenge);
                } else {
                    $challenge->save();
                }

                return ['error' => 'The verification code is incorrect.'];
            }

            if (mb_strtolower(trim($challenge->email)) !== mb_strtolower(trim($offer->candidate_email))
                || mb_strtolower(trim($application->email)) !== mb_strtolower(trim($offer->candidate_email))) {
                return ['error' => 'The candidate email no longer matches this offer. Contact recruitment for help.'];
            }
            $existingUser = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($offer->candidate_email))])->lockForUpdate()->first();
            $reusableMembership = null;
            if ($existingUser) {
                $hasRejectedCareersHire = $existingUser->isStaff()
                    && EmploymentOffer::query()->where('employee_user_id', $existingUser->id)
                        ->where('company_id', $offer->company_id)->where('status', 'accepted')
                        ->whereHas('application', fn ($query) => $query->where('status', 'rejected'))->exists();
                $inactiveMemberships = BusinessMembership::query()->where('user_id', $existingUser->id)
                    ->where('company_id', $offer->company_id)->where('is_active', false)->lockForUpdate()->get();
                $hasActiveMembership = BusinessMembership::query()->where('user_id', $existingUser->id)->where('is_active', true)->exists();
                if (! $hasRejectedCareersHire || $hasActiveMembership || $inactiveMemberships->count() !== 1
                    || ! in_array($inactiveMemberships->first()->role, ['seller', 'manager'], true)) {
                    return ['error' => 'We could not complete account setup. Contact PawHaven recruitment for assistance.'];
                }
                $reusableMembership = $inactiveMemberships->first();
            }

            $challenge->verified_at = now();
            $challenge->consumed_at = now();
            $challenge->active_offer_id = null;
            $challenge->save();

            if ($existingUser) {
                $user = $existingUser;
                $user->name = $application->full_name;
                $user->email_verified_at ??= now();
                $user->save();
                $membership = $reusableMembership;
                $membership->branch_id = $offer->branch_id;
                $membership->role = $offer->position_type;
                $membership->save();
            } else {
                $user = User::forceCreate([
                    'name' => $application->full_name,
                    'email' => $offer->candidate_email,
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'staff',
                    'email_verified_at' => now(),
                ]);
                $membership = BusinessMembership::create([
                    'user_id' => $user->id,
                    'company_id' => $offer->company_id,
                    'branch_id' => $offer->branch_id,
                    'role' => $offer->position_type,
                    'is_active' => false,
                ]);
            }
            $offer->status = 'accepted';
            $offer->accepted_at = now();
            $offer->active_application_id = null;
            $offer->employee_user_id = $user->id;
            $offer->save();
            $application->status = 'awaiting_owner_approval';
            $application->reviewed_at = now();
            $application->save();

            \App\Models\AdminAuditLog::record('careers.offer.accepted', 'Candidate verified and accepted an employment offer.', [
                'company_id' => $offer->company_id,
                'branch_id' => $offer->branch_id,
                'job_posting_id' => $application->job_posting_id,
                'application_id' => $application->id,
                'offer_id' => $offer->id,
                'employee_user_id' => $user->id,
            ]);
            \App\Models\AdminAuditLog::record('careers.employee.pending_owner_approval', 'Employee account created pending Shop Owner approval.', [
                'company_id' => $offer->company_id,
                'branch_id' => $offer->branch_id,
                'application_id' => $application->id,
                'offer_id' => $offer->id,
                'employee_user_id' => $user->id,
                'membership_id' => $membership->id,
            ]);

            return ['user' => $user];
        });

        if (isset($result['error'])) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        /** @var User $user */
        $user = $result['user'];
        $membership = BusinessMembership::query()->where('user_id', $user->id)->where('is_active', false)->first();
        $owner = BusinessMembership::query()->where('company_id', $membership?->company_id)
            ->where('role', 'owner')->where('is_active', true)->whereNull('branch_id')->with('user')->first()?->user;
        $branch = $membership ? Branch::query()->withoutGlobalScopes()->find($membership->branch_id) : null;
        if ($owner && $branch) {
            try {
                Mail::to($owner->email)->send(new PendingEmployeeApprovalMail(
                    $user->name,
                    $membership->role === 'manager' ? 'Branch Manager' : 'Seller / Sales Associate',
                    $branch->name,
                ));
            } catch (Throwable $exception) {
                Log::warning('Shop Owner approval notification could not be sent.', [
                    'employee_user_id' => $user->id,
                    'error_type' => get_class($exception),
                ]);
            }
        }

        return $user;
    }

    private function assertOfferValid(EmploymentOffer $offer, ?string $rawToken = null, ?string $sessionTokenHash = null): void
    {
        $validToken = $rawToken !== null
            ? hash_equals($offer->token_hash, $this->recruitment->tokenHash($rawToken))
            : ($sessionTokenHash !== null && hash_equals($offer->token_hash, $sessionTokenHash));
        if (! $offer->isOpen() || $offer->active_application_id !== $offer->job_application_id || ! $validToken) {
            throw ValidationException::withMessages(['offer' => 'This offer is expired, revoked, or no longer valid.']);
        }
        $this->recruitment->assertCompanyAndBranchActive($offer);
    }

    private function otpHash(EmploymentOffer $offer, string $otp): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('The application encryption key is not configured.');
        }

        return hash_hmac('sha256', $offer->id.'|'.mb_strtolower($offer->candidate_email).'|'.$otp, $key);
    }

    private function invalidate(EmploymentOfferOtpChallenge $challenge): void
    {
        $challenge->active_offer_id = null;
        $challenge->invalidated_at = now();
        $challenge->save();
    }
}
