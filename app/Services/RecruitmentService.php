<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\EmploymentOffer;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecruitmentService
{
    public const OFFER_DAYS = 7;

    public const ACTIVE_APPLICATION_STATUSES = ['submitted', 'shortlisted', 'selected', 'offered', 'awaiting_owner_approval'];

    public function createPosting(User $actor, array $data): JobPosting
    {
        $company = app(BusinessAccessService::class)->installationCompany();
        $branches = $this->validatedBranches($company, $data['branch_ids'] ?? []);

        $posting = DB::transaction(function () use ($actor, $company, $data, $branches): JobPosting {
            $lockedCompany = Company::query()->withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            if (! $lockedCompany->is_active) {
                throw ValidationException::withMessages(['company' => 'The company is inactive.']);
            }

            $slugBase = Str::slug($data['title']) ?: Str::uuid()->toString();
            $slug = $slugBase;
            $suffix = 2;
            while (JobPosting::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase.'-'.$suffix++;
            }

            $status = $data['status'];
            $posting = JobPosting::create([
                'company_id' => $company->id,
                'created_by_user_id' => $actor->id,
                'title' => $data['title'],
                'slug' => $slug,
                'position_type' => $data['position_type'],
                'summary' => $data['summary'],
                'description' => $data['description'],
                'responsibilities' => $data['responsibilities'] ?? null,
                'requirements' => $data['requirements'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'openings' => $data['openings'] ?? 1,
                'application_deadline' => $data['application_deadline'] ?? null,
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
                'closed_at' => $status === 'closed' ? now() : null,
                'archived_at' => $status === 'archived' ? now() : null,
            ]);
            $posting->branches()->sync($branches->mapWithKeys(fn (Branch $branch): array => [
                $branch->id => ['company_id' => $company->id],
            ])->all());
            AdminAuditLog::record('careers.vacancy.created', 'Created a Careers vacancy.', [
                'company_id' => $company->id,
                'job_posting_id' => $posting->id,
            ]);

            return $posting;
        });

        return $posting;
    }

    public function updatePosting(JobPosting $posting, User $actor, array $data): JobPosting
    {
        $company = app(BusinessAccessService::class)->installationCompany((int) $posting->company_id);
        $branches = $this->validatedBranches($company, $data['branch_ids'] ?? []);

        return DB::transaction(function () use ($posting, $actor, $data, $branches): JobPosting {
            $locked = JobPosting::query()->whereKey($posting->id)->lockForUpdate()->firstOrFail();
            $removedBranches = $locked->branches()->withoutGlobalScopes()
                ->whereNotIn('branches.id', $branches->modelKeys())->pluck('branches.id');
            if ($removedBranches->isNotEmpty()
                && JobApplication::query()->where('job_posting_id', $locked->id)
                    ->whereIn('preferred_branch_id', $removedBranches)->exists()) {
                throw ValidationException::withMessages(['branch_ids' => 'A location selected by an existing candidate cannot be removed from this vacancy.']);
            }
            $locked->fill([
                'title' => $data['title'],
                'position_type' => $data['position_type'],
                'summary' => $data['summary'],
                'description' => $data['description'],
                'responsibilities' => $data['responsibilities'] ?? null,
                'requirements' => $data['requirements'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'openings' => $data['openings'] ?? 1,
                'application_deadline' => $data['application_deadline'] ?? null,
            ]);
            $this->setPostingStatus($locked, $data['status']);
            $locked->save();
            $locked->branches()->sync($branches->mapWithKeys(fn (Branch $branch): array => [
                $branch->id => ['company_id' => $locked->company_id],
            ])->all());
            AdminAuditLog::record('careers.vacancy.updated', 'Updated a Careers vacancy.', [
                'company_id' => $locked->company_id,
                'job_posting_id' => $locked->id,
                'actor_id' => $actor->id,
            ]);

            return $locked->fresh('branches');
        });
    }

    public function submitApplication(JobPosting $posting, array $data): JobApplication
    {
        $cvPath = $data['cv_path'] ?? null;
        $email = mb_strtolower(trim($data['email']));
        $key = hash('sha256', $posting->id.'|'.$email);

        try {
            return DB::transaction(function () use ($posting, $data, $cvPath, $email, $key): JobApplication {
                $lockedPosting = JobPosting::query()->whereKey($posting->id)->lockForUpdate()->firstOrFail();
                $companyIsActive = DB::table('companies')->where('id', $lockedPosting->company_id)->where('is_active', true)->exists();
                if (! $lockedPosting->isAcceptingApplications() || ! $companyIsActive) {
                    throw ValidationException::withMessages(['vacancy' => 'This vacancy is no longer accepting applications.']);
                }

                $preferredBranchId = $data['preferred_branch_id'] ?? null;
                if ($preferredBranchId !== null) {
                    $allowed = $lockedPosting->branches()->whereKey($preferredBranchId)->where('branches.is_active', true)->exists();
                    if (! $allowed) {
                        throw ValidationException::withMessages(['preferred_branch_id' => 'Choose one of the listed active locations.']);
                    }
                }

                if (JobApplication::query()->where('active_submission_key', $key)->exists()) {
                    throw ValidationException::withMessages(['email' => 'An active application already exists for this vacancy and email.']);
                }

                $application = JobApplication::create([
                    'job_posting_id' => $lockedPosting->id,
                    'preferred_branch_id' => $preferredBranchId,
                    'full_name' => $data['full_name'],
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'experience' => $data['experience'] ?? null,
                    'motivation' => $data['motivation'] ?? null,
                    'cv_path' => $cvPath,
                    'status' => 'submitted',
                    'active_submission_key' => $key,
                    'submitted_at' => now(),
                ]);
                AdminAuditLog::record('careers.application.submitted', 'A candidate submitted a Careers application.', [
                    'company_id' => $lockedPosting->company_id,
                    'job_posting_id' => $lockedPosting->id,
                    'application_id' => $application->id,
                ]);

                return $application;
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'active_submission_key')) {
                throw ValidationException::withMessages(['email' => 'An active application already exists for this vacancy and email.']);
            }

            throw $exception;
        }
    }

    public function reviewApplication(JobApplication $application, User $actor, string $status, ?string $notes): JobApplication
    {
        return DB::transaction(function () use ($application, $actor, $status, $notes): JobApplication {
            $locked = JobApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $allowed = match ($locked->status) {
                'submitted' => ['shortlisted', 'selected', 'rejected'],
                'shortlisted' => ['selected', 'rejected'],
                'selected' => ['rejected'],
                default => [],
            };
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This application can no longer move to that status.']);
            }

            $locked->status = $status;
            $locked->internal_notes = $notes;
            $locked->reviewed_at = now();
            if ($status === 'rejected') {
                $locked->active_submission_key = null;
            }
            $locked->save();
            AdminAuditLog::record('careers.application.reviewed', 'Reviewed a Careers application.', [
                'company_id' => $locked->posting->company_id,
                'job_posting_id' => $locked->job_posting_id,
                'application_id' => $locked->id,
                'status' => $status,
                'actor_id' => $actor->id,
            ]);

            return $locked;
        });
    }

    /** Create a new offer and deliver the raw token only in the email. */
    public function issueOffer(JobApplication $application, User $actor, int $branchId, string $terms, ?string $compensation, \DateTimeInterface $deadline): EmploymentOffer
    {
        $initialApplication = JobApplication::query()->with('posting')->findOrFail($application->id);
        $company = app(BusinessAccessService::class)->installationCompany((int) $initialApplication->posting->company_id);

        [$offer, $token] = DB::transaction(function () use ($application, $actor, $branchId, $terms, $compensation, $deadline, $company): array {
            $lockedCompany = Company::query()->withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $lockedApplication = JobApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($lockedApplication->status !== 'selected') {
                throw ValidationException::withMessages(['application' => 'Select the candidate before issuing an offer.']);
            }

            if (! $lockedCompany->is_active) {
                throw ValidationException::withMessages(['company' => 'The company is inactive.']);
            }
            if ($deadline <= now()) {
                throw ValidationException::withMessages(['acceptance_deadline' => 'The offer deadline must be in the future.']);
            }
            $branch = Branch::query()->withoutGlobalScopes()->whereKey($branchId)->lockForUpdate()->first();
            if (! $branch || ! $branch->is_active || (int) $branch->company_id !== (int) $lockedCompany->id) {
                throw ValidationException::withMessages(['branch_id' => 'Choose an active branch belonging to this company.']);
            }
            EmploymentOffer::query()->where('job_application_id', $lockedApplication->id)
                ->where('active_application_id', $lockedApplication->id)
                ->lockForUpdate()->get()->each(function (EmploymentOffer $old): void {
                    $old->active_application_id = null;
                    if ($old->status === 'offered') {
                        $old->status = 'revoked';
                        $old->revoked_at = now();
                    }
                    $old->save();
                    $this->invalidateChallenges($old);
                });

            $token = Str::random(64);
            $offer = EmploymentOffer::create([
                'job_application_id' => $lockedApplication->id,
                'company_id' => $lockedCompany->id,
                'branch_id' => $branch->id,
                'offered_by_user_id' => $actor->id,
                'active_application_id' => $lockedApplication->id,
                'position_type' => $lockedApplication->posting->position_type,
                'candidate_email' => mb_strtolower(trim($lockedApplication->email)),
                'employment_terms' => $terms,
                'compensation' => $compensation,
                'acceptance_deadline' => $deadline,
                'status' => 'offered',
                'token_hash' => $this->tokenHash($token),
                'issued_at' => now(),
            ]);
            $lockedApplication->status = 'offered';
            $lockedApplication->reviewed_at = now();
            $lockedApplication->save();
            AdminAuditLog::record('careers.offer.issued', 'Issued a formal Careers offer.', [
                'company_id' => $lockedCompany->id,
                'branch_id' => $branch->id,
                'job_posting_id' => $lockedApplication->job_posting_id,
                'application_id' => $lockedApplication->id,
                'offer_id' => $offer->id,
                'actor_id' => $actor->id,
            ]);

            return [$offer, $token];
        });

        $this->deliverOffer($offer, $token);

        return $offer;
    }

    public function resendOffer(EmploymentOffer $offer): void
    {
        [$lockedOffer, $token] = DB::transaction(function () use ($offer): array {
            Company::query()->withoutGlobalScopes()->whereKey($offer->company_id)->lockForUpdate()->firstOrFail();
            JobApplication::query()->whereKey($offer->job_application_id)->lockForUpdate()->firstOrFail();
            $locked = EmploymentOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen() || $locked->active_application_id !== $locked->job_application_id) {
                throw ValidationException::withMessages(['offer' => 'This offer is no longer available to resend.']);
            }
            Branch::query()->withoutGlobalScopes()->whereKey($locked->branch_id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyAndBranchActive($locked);
            $token = Str::random(64);
            $locked->token_hash = $this->tokenHash($token);
            $locked->resend_count++;
            $locked->save();
            $this->invalidateChallenges($locked);
            AdminAuditLog::record('careers.offer.resent', 'Resent a Careers offer with a rotated token.', [
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'offer_id' => $locked->id,
            ]);

            return [$locked, $token];
        });
        $this->deliverOffer($lockedOffer, $token);
    }

    public function revokeOffer(EmploymentOffer $offer, User $actor): void
    {
        DB::transaction(function () use ($offer, $actor): void {
            Company::query()->withoutGlobalScopes()->whereKey($offer->company_id)->lockForUpdate()->firstOrFail();
            JobApplication::query()->whereKey($offer->job_application_id)->lockForUpdate()->firstOrFail();
            $locked = EmploymentOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'offered') {
                throw ValidationException::withMessages(['offer' => 'Only an outstanding offer can be revoked.']);
            }
            $locked->status = 'revoked';
            $locked->revoked_at = now();
            $locked->active_application_id = null;
            $locked->save();
            $this->invalidateChallenges($locked);
            $locked->application()->where('status', 'offered')->update(['status' => 'selected']);
            AdminAuditLog::record('careers.offer.revoked', 'Revoked a Careers offer.', [
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'offer_id' => $locked->id,
                'actor_id' => $actor->id,
            ]);
        });
    }

    public function declineOffer(string $rawToken): void
    {
        $initialOffer = EmploymentOffer::query()->where('token_hash', $this->tokenHash($rawToken))->first();
        if (! $initialOffer) {
            throw ValidationException::withMessages(['offer' => 'This offer is expired, revoked, or no longer available.']);
        }
        DB::transaction(function () use ($rawToken, $initialOffer): void {
            Company::query()->withoutGlobalScopes()->whereKey($initialOffer->company_id)->lockForUpdate()->firstOrFail();
            JobApplication::query()->whereKey($initialOffer->job_application_id)->lockForUpdate()->firstOrFail();
            $offer = EmploymentOffer::query()->whereKey($initialOffer->id)->lockForUpdate()->first();
            if (! $offer || ! $offer->isOpen() || $offer->active_application_id !== $offer->job_application_id) {
                throw ValidationException::withMessages(['offer' => 'This offer is expired, revoked, or no longer available.']);
            }
            $this->assertCompanyAndBranchActive($offer);
            $offer->status = 'declined';
            $offer->active_application_id = null;
            $offer->save();
            $this->invalidateChallenges($offer);
            $application = JobApplication::query()->whereKey($offer->job_application_id)->firstOrFail();
            $application->status = 'withdrawn';
            $application->active_submission_key = null;
            $application->save();
            AdminAuditLog::record('careers.offer.declined', 'Candidate declined a Careers employment offer.', [
                'company_id' => $offer->company_id,
                'branch_id' => $offer->branch_id,
                'application_id' => $application->id,
                'offer_id' => $offer->id,
            ]);
        });
    }

    public function approveEmployee(User $approver, EmploymentOffer $offer): void
    {
        $access = app(BusinessAccessService::class);
        if (! $access->canManageEmployees($approver, (int) $offer->company_id)) {
            throw new AuthorizationException('Only the active Shop Owner can approve employee access.');
        }

        $userId = DB::transaction(function () use ($approver, $offer, $access): int {
            $company = Company::query()->withoutGlobalScopes()->whereKey($offer->company_id)->lockForUpdate()->firstOrFail();
            $ownerMembership = BusinessMembership::query()->where('user_id', $approver->id)
                ->where('company_id', $company->id)->where('role', BusinessAccessService::OWNER)
                ->whereNull('branch_id')->where('is_active', true)->lockForUpdate()->first();
            if (! $ownerMembership || ! $company->is_active || ! $access->canManageEmployees($approver, (int) $company->id)) {
                throw new AuthorizationException('Shop Owner approval is required.');
            }

            $application = JobApplication::query()->whereKey($offer->job_application_id)->lockForUpdate()->firstOrFail();
            $locked = EmploymentOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'accepted'
                || $application->status !== 'awaiting_owner_approval'
                || $locked->employee_user_id === null) {
                throw ValidationException::withMessages(['offer' => 'This hire is not awaiting Owner approval.']);
            }

            $branch = Branch::query()->withoutGlobalScopes()->whereKey($locked->branch_id)->lockForUpdate()->first();
            if (! $company->is_active || ! $branch || ! $branch->is_active || (int) $branch->company_id !== (int) $company->id) {
                throw ValidationException::withMessages(['branch' => 'The assigned company or branch is no longer active.']);
            }

            $membership = BusinessMembership::query()->where('user_id', $locked->employee_user_id)
                ->where('company_id', $company->id)->lockForUpdate()->firstOrFail();
            if ($membership->is_active || $membership->role !== $locked->position_type || (int) $membership->branch_id !== (int) $branch->id) {
                throw ValidationException::withMessages(['membership' => 'The pending employee assignment is inconsistent.']);
            }
            $membership->is_active = true;
            $membership->save();
            $application->status = 'hired';
            $application->active_submission_key = null;
            $application->save();
            AdminAuditLog::record('careers.employee.approved', 'Shop Owner approved employee business access.', [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'application_id' => $application->id,
                'offer_id' => $locked->id,
                'employee_user_id' => $locked->employee_user_id,
                'approver_id' => $approver->id,
            ]);

            return (int) $locked->employee_user_id;
        });

        $this->sendPasswordSetupLink($userId);
    }

    public function rejectEmployee(User $approver, EmploymentOffer $offer): void
    {
        if (! app(BusinessAccessService::class)->canManageEmployees($approver, (int) $offer->company_id)) {
            throw new AuthorizationException('Only the active Shop Owner can reject employee access.');
        }

        DB::transaction(function () use ($approver, $offer): void {
            $company = Company::query()->withoutGlobalScopes()->whereKey($offer->company_id)->lockForUpdate()->firstOrFail();
            $application = JobApplication::query()->whereKey($offer->job_application_id)->lockForUpdate()->firstOrFail();
            $lockedOffer = EmploymentOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if (! app(BusinessAccessService::class)->canManageEmployees($approver, (int) $company->id)
                || $lockedOffer->status !== 'accepted'
                || $application->status !== 'awaiting_owner_approval') {
                throw new AuthorizationException('This employee is not available for Owner review.');
            }
            $application->status = 'rejected';
            $application->active_submission_key = null;
            $application->save();
            AdminAuditLog::record('careers.employee.rejected', 'Shop Owner declined employee business access.', [
                'company_id' => $company->id,
                'branch_id' => $lockedOffer->branch_id,
                'application_id' => $application->id,
                'offer_id' => $lockedOffer->id,
                'approver_id' => $approver->id,
            ]);
        });
    }

    public function validateOffer(string $rawToken): ?EmploymentOffer
    {
        $hash = $this->tokenHash($rawToken);
        $offer = EmploymentOffer::query()->with(['application.posting'])->where('token_hash', $hash)->first();
        if (! $offer || ! $offer->isOpen() || $offer->active_application_id !== $offer->job_application_id) {
            return null;
        }
        if (! $this->companyAndBranchAreActive($offer)) {
            return null;
        }
        $offer->setRelation('branch', Branch::query()->withoutGlobalScopes()->find($offer->branch_id));

        return $offer;
    }

    public function offerWasAccepted(string $rawToken): bool
    {
        return EmploymentOffer::query()->where('token_hash', $this->tokenHash($rawToken))
            ->where('status', 'accepted')->whereNotNull('accepted_at')->whereNotNull('employee_user_id')->exists();
    }

    public function tokenHash(string $token): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('The application encryption key is not configured.');
        }

        return hash_hmac('sha256', $token, $key);
    }

    public function invalidateChallenges(EmploymentOffer $offer): void
    {
        $offer->otpChallenges()->whereNull('consumed_at')->whereNull('invalidated_at')->update([
            'active_offer_id' => null,
            'invalidated_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function assertCompanyAndBranchActive(EmploymentOffer $offer): void
    {
        if (! $this->companyAndBranchAreActive($offer)) {
            throw ValidationException::withMessages(['offer' => 'The offer company or branch is no longer active.']);
        }
    }

    private function companyAndBranchAreActive(EmploymentOffer $offer): bool
    {
        try {
            app(BusinessAccessService::class)->installationCompany((int) $offer->company_id);
        } catch (AuthorizationException) {
            return false;
        }

        return Company::query()->withoutGlobalScopes()->whereKey($offer->company_id)->where('is_active', true)->exists()
            && Branch::query()->withoutGlobalScopes()->whereKey($offer->branch_id)
                ->where('company_id', $offer->company_id)->where('is_active', true)->exists();
    }

    private function validatedBranches(Company $company, array $branchIds)
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));
        $branches = Branch::query()->withoutGlobalScopes()->where('company_id', $company->id)
            ->where('is_active', true)->whereIn('id', $branchIds)->get();
        if ($branches->count() !== count($branchIds)) {
            throw ValidationException::withMessages(['branch_ids' => 'Select only active branches belonging to the company.']);
        }

        return $branches;
    }

    private function setPostingStatus(JobPosting $posting, string $status): void
    {
        if (! in_array($status, JobPosting::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Select a supported vacancy status.']);
        }
        if ($status === 'published' && ! $posting->published_at) {
            $posting->published_at = now();
        }
        if ($status === 'closed') {
            $posting->closed_at = now();
        } elseif ($posting->status === 'closed') {
            $posting->closed_at = null;
        }
        if ($status === 'archived') {
            $posting->archived_at = now();
        } elseif ($posting->status === 'archived') {
            $posting->archived_at = null;
        }
        $posting->status = $status;
    }

    private function deliverOffer(EmploymentOffer $offer, string $rawToken): void
    {
        try {
            $offer->setRelation('branch', Branch::query()->withoutGlobalScopes()->findOrFail($offer->branch_id));
            Mail::to($offer->candidate_email)->send(new \App\Mail\EmploymentOfferMail($offer, $rawToken));
        } catch (Throwable $exception) {
            Log::error('Careers offer delivery failed.', [
                'offer_id' => $offer->id,
                'error_type' => get_class($exception),
            ]);
            throw ValidationException::withMessages(['offer' => 'The offer was saved but could not be emailed. Use Resend to issue a new secure link.']);
        }
    }

    private function sendPasswordSetupLink(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
            if ($status !== Password::RESET_LINK_SENT) {
                Log::warning('Employee password setup email could not be sent.', ['user_id' => $userId, 'status' => $status]);
            }
        } catch (Throwable $exception) {
            Log::error('Employee password setup email delivery failed.', [
                'user_id' => $userId,
                'error_type' => get_class($exception),
            ]);
        }
    }
}
