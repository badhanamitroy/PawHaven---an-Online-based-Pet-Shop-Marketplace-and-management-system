<?php

namespace App\Services;

use App\Mail\BranchManagerInvitationMail;
use App\Mail\SellerInvitationMail;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public const DEFAULT_EXPIRY_HOURS = 72;

    /**
     * Create a new invitation and deliver the official email.
     *
     * @param  array{email: string, name?: string|null, role: string, branch_id?: int|null, company_id?: int|null}  $data
     * @return array{invitation: Invitation, raw_token: string}
     *
     * @throws ValidationException|Throwable
     */
    public function createInvitation(array $data, User $inviter): array
    {
        $email = strtolower(trim($data['email']));
        $role = $data['role'];
        $isManager = in_array($role, ['manager', 'branch_manager'], true);
        $branchId = $isManager ? (int) ($data['branch_id'] ?? 0) : null;
        $branch = $isManager
            ? Branch::query()->withoutGlobalScopes()->whereKey($branchId)->first()
            : null;
        if ($isManager && ! $branch) {
            throw ValidationException::withMessages(['branch_id' => 'Choose a valid branch for the Branch Manager invitation.']);
        }

        $company = app(BusinessAccessService::class)->installationCompany($branch?->company_id);
        $this->authorizeInvitationRole($inviter, $role, (int) $company->id);
        $branch = null;
        if ($isManager) {
            $branch = Branch::query()->withoutGlobalScopes()
                ->whereKey($branchId)->where('company_id', $company->id)->where('is_active', true)->first();
            if (! $branch) {
                throw ValidationException::withMessages(['branch_id' => 'The selected branch is inactive or belongs to another company.']);
            }
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = $this->hashToken($rawToken);
        $activeEmailKey = $this->activeEmailKey($email);
        $expiresAt = now()->addHours(self::DEFAULT_EXPIRY_HOURS);

        /** @var Invitation $invitation */
        $invitation = DB::transaction(function () use ($inviter, $data, $email, $role, $company, $branch, $tokenHash, $activeEmailKey, $expiresAt): Invitation {
            Company::query()->withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $lockedCompany = app(BusinessAccessService::class)->installationCompany((int) $company->id);
            if (! $lockedCompany->is_active) {
                throw ValidationException::withMessages(['company_id' => 'Invitations cannot be issued for an inactive company.']);
            }
            if ($branch !== null) {
                $branch = Branch::query()->withoutGlobalScopes()->whereKey($branch->id)
                    ->lockForUpdate()->first();
                if (! $branch || ! $branch->is_active || (int) $branch->company_id !== (int) $lockedCompany->id) {
                    throw ValidationException::withMessages(['branch_id' => 'The selected branch is inactive or belongs to another company.']);
                }
            }
            Invitation::query()->where('company_id', $lockedCompany->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '<=', now())
                ->update(['active_email_key' => null]);
            $this->assertNoExistingAccountOrPendingInvitation($email);

            $invitation = Invitation::create([
                'email' => $email,
                'name' => isset($data['name']) && trim((string) $data['name']) !== '' ? trim((string) $data['name']) : null,
                'role' => $role === 'branch_manager' ? 'manager' : $role,
                'company_id' => $lockedCompany->id,
                'branch_id' => $branch?->id,
                'token_hash' => $tokenHash,
                'active_email_key' => $activeEmailKey,
                'invited_by_user_id' => $inviter->id,
                'expires_at' => $expiresAt,
            ]);
            AdminAuditLog::record(
                'invitation.created',
                "Invitation issued to {$invitation->email} for {$invitation->roleLabel()} role.",
                [
                    'invitation_id' => $invitation->id,
                    'role' => $invitation->role,
                    'branch_id' => $invitation->branch_id,
                    'company_id' => $invitation->company_id,
                ]
            );

            return $invitation;
        });

        $this->deliverInvitationEmail($invitation, $rawToken, $branch);

        return [
            'invitation' => $invitation,
            'raw_token' => $rawToken,
        ];
    }

    /**
     * Resend an eligible invitation with a newly rotated token and fresh expiry.
     *
     * @return array{invitation: Invitation, raw_token: string}
     *
     * @throws ValidationException|Throwable
     */
    public function resendInvitation(Invitation $invitation, User $actor): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = $this->hashToken($rawToken);
        $expiresAt = now()->addHours(self::DEFAULT_EXPIRY_HOURS);

        $updated = DB::transaction(function () use ($invitation, $actor, $tokenHash, $expiresAt, $rawToken): Invitation {
            Company::query()->withoutGlobalScopes()->whereKey($invitation->company_id)->lockForUpdate()->firstOrFail();
            $lockedCompany = app(BusinessAccessService::class)->installationCompany((int) $invitation->company_id);
            /** @var Invitation|null $current */
            $current = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->first();
            if (! $current || $current->isAccepted() || $current->isRevoked()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer eligible for resend.']);
            }
            $this->authorizeInvitationRole($actor, $current->role, (int) $current->company_id);
            if (! $lockedCompany->is_active) {
                throw ValidationException::withMessages(['invitation' => 'An invitation for an inactive company cannot be resent.']);
            }
            if ($this->hasExistingAccount($current->email)) {
                throw ValidationException::withMessages(['invitation' => 'A user account with this email address already exists.']);
            }

            $branch = null;
            if ($current->isBranchManager()) {
                $branch = Branch::query()->withoutGlobalScopes()->whereKey($current->branch_id)
                    ->lockForUpdate()->first();
                if (! $branch || ! $branch->is_active || (int) $branch->company_id !== (int) $lockedCompany->id) {
                    throw ValidationException::withMessages(['invitation' => 'The assigned branch is no longer active in this company.']);
                }
            }

            $current->forceFill([
                'token_hash' => $tokenHash,
                'active_email_key' => $this->activeEmailKey($current->email),
                'expires_at' => $expiresAt,
            ])->save();

            // Keep the invitation row locked until delivery finishes so acceptance cannot consume
            // the newly rotated token before the corresponding email is dispatched.
            $this->deliverInvitationEmail($current, $rawToken, $branch);
            AdminAuditLog::record(
                'invitation.resent',
                "Invitation refreshed and resent to {$current->email} ({$current->roleLabel()}).",
                [
                    'invitation_id' => $current->id,
                    'role' => $current->role,
                    'branch_id' => $current->branch_id,
                    'company_id' => $current->company_id,
                ]
            );

            return $current;
        });

        return [
            'invitation' => $updated->fresh(),
            'raw_token' => $rawToken,
        ];
    }

    /**
     * Revoke an outstanding invitation.
     */
    public function revokeInvitation(Invitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            Company::query()->withoutGlobalScopes()->whereKey($invitation->company_id)->lockForUpdate()->firstOrFail();
            $current = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $this->authorizeInvitationRole($actor, $current->role, (int) $current->company_id);
            if ($current->isAccepted()) {
                throw ValidationException::withMessages(['invitation' => 'An accepted invitation cannot be revoked.']);
            }
            if ($current->isRevoked()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation has already been revoked.']);
            }

            $current->forceFill(['revoked_at' => now(), 'active_email_key' => null])->save();
            AdminAuditLog::record(
                'invitation.revoked',
                "Invitation to {$current->email} ({$current->roleLabel()}) was revoked by {$actor->name}.",
                [
                    'invitation_id' => $current->id,
                    'role' => $current->role,
                    'branch_id' => $current->branch_id,
                    'company_id' => $current->company_id,
                ]
            );
        });
    }

    /**
     * Locate an invitation record by raw token without exposing errors.
     */
    public function findByRawToken(string $rawToken): ?Invitation
    {
        if (strlen($rawToken) < 32) {
            return null;
        }

        $tokenHash = $this->hashToken($rawToken);

        return Invitation::query()
            ->with(['branch', 'company', 'invitedBy'])
            ->where('token_hash', $tokenHash)
            ->first();
    }

    /**
     * Atomically accept an invitation, create the candidate user, assign role/branch, and consume the token.
     *
     * @param  array{name: string, password: string}  $userData
     *
     * @throws ValidationException
     */
    public function acceptInvitation(Invitation $invitation, string $submittedToken, array $userData): User
    {
        return DB::transaction(function () use ($invitation, $submittedToken, $userData): User {
            $company = Company::query()->withoutGlobalScopes()
                ->whereKey($invitation->company_id)->lockForUpdate()->first();
            /** @var Invitation|null $locked */
            $locked = Invitation::query()
                ->lockForUpdate()
                ->whereKey($invitation->id)
                ->first();

            if (! $company
                || ! $locked
                || ! $locked->isPending()
                || ! hash_equals($locked->token_hash, $this->hashToken($submittedToken))) {
                throw ValidationException::withMessages([
                    'email' => 'This invitation link is no longer valid. Request a fresh invitation if needed.',
                ]);
            }

            try {
                $company = app(BusinessAccessService::class)->installationCompany((int) $company->id);
            } catch (AuthorizationException) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is no longer valid because its company is unavailable.',
                ]);
            }
            if (! $company->is_active) {
                throw ValidationException::withMessages(['email' => 'This invitation belongs to an inactive company.']);
            }

            if ($this->hasExistingAccount($locked->email)) {
                throw ValidationException::withMessages([
                    'email' => 'An account with this email address already exists.',
                ]);
            }

            if ($locked->isBranchManager()) {
                $branch = Branch::query()
                    ->withoutGlobalScopes()
                    ->whereKey($locked->branch_id)
                    ->where('company_id', $locked->company_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $branch) {
                    throw ValidationException::withMessages([
                        'invitation' => 'The assigned branch is no longer available. Please contact PawHaven Administration.',
                    ]);
                }
            }

            $role = $locked->isSeller() ? 'seller' : 'staff';

            $user = new User([
                'name' => $userData['name'],
                'email' => $locked->email,
                'password' => $userData['password'],
            ]);
            $user->role = $role;
            $user->email_verified_at = null;
            $user->save();

            $locked->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
                'active_email_key' => null,
            ])->save();

            if ($locked->isBranchManager()) {
                app(BusinessMembershipService::class)->assignForInvitation(
                    $user,
                    $locked,
                );
            }

            AdminAuditLog::record(
                'invitation.accepted',
                "Invitation accepted by {$user->name} ({$user->email}) for {$locked->roleLabel()} role.",
                [
                    'invitation_id' => $locked->id,
                    'user_id' => $user->id,
                    'role' => $locked->role,
                    'branch_id' => $locked->branch_id,
                    'company_id' => $locked->company_id,
                ]
            );

            return $user;
        });
    }

    public function hashToken(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, $this->applicationKey());
    }

    private function activeEmailKey(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public function hasExistingAccount(string $email): bool
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->exists();
    }

    private function assertNoExistingAccountOrPendingInvitation(string $email): void
    {
        if ($this->hasExistingAccount($email)) {
            throw ValidationException::withMessages(['email' => 'A user account with this email address already exists.']);
        }

        if (Invitation::query()->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An active invitation for this email address is already pending. You can resend or revoke it below.',
            ]);
        }
    }

    private function authorizeInvitationRole(User $actor, string $role, int $companyId): void
    {
        if (in_array($role, ['manager', 'branch_manager'], true)) {
            if (! app(BusinessAccessService::class)->canManageEmployees($actor, $companyId)) {
                throw new AuthorizationException('Only the Shop Owner can manage employee invitations.');
            }

            return;
        }

        if ($role !== 'seller' || ! $actor->isAdmin()) {
            throw new AuthorizationException('This actor cannot manage marketplace Seller invitations.');
        }
    }

    private function deliverInvitationEmail(Invitation $invitation, string $rawToken, ?Branch $branch): void
    {
        try {
            if ($invitation->isSeller()) {
                Mail::to($invitation->email)->send(new SellerInvitationMail($invitation, $rawToken));
            } else {
                $branch ??= $invitation->branch;
                if (! $branch) {
                    throw new \RuntimeException('Branch details missing for Branch Manager invitation delivery.');
                }
                Mail::to($invitation->email)->send(new BranchManagerInvitationMail($invitation, $rawToken, $branch));
            }
        } catch (Throwable $exception) {
            Log::error('Failed to deliver invitation email.', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'error_type' => get_class($exception),
            ]);

            throw new \RuntimeException('We could not deliver the invitation email. Please verify your mail settings and retry.', 0, $exception);
        }
    }

    private function applicationKey(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('The application encryption key is not configured.');
        }

        return $key;
    }
}
