<?php

namespace Tests\Feature\Admin;

use App\Mail\BranchManagerInvitationMail;
use App\Mail\LoginOtpMail;
use App\Mail\SellerInvitationMail;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class InvitationOnboardingTest extends TestCase
{
    use CreatesBusinessMemberships;
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Company $company;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->where('slug', 'pawhaven')->firstOrFail();

        $this->branch = Branch::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Central Hub Branch',
            'code' => 'CH-01',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@pawhaven.test',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Shop Owner',
            'email' => 'owner@pawhaven.test',
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($this->owner);
    }

    public function test_admin_can_view_marketplace_seller_invitation_pages(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.invitations.index'));
        $response->assertOk();
        $response->assertSee('Staff &amp; Seller Invitations', false);
        $response->assertSee('Issue New Invitation');

        $createResponse = $this->actingAs($this->admin)->get(route('admin.invitations.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Issue Exceptional Invitation');
        $createResponse->assertSee('Marketplace Seller');
        $createResponse->assertDontSee('value="manager"', false);
    }

    public function test_shop_owner_can_view_branch_manager_invitation_pages(): void
    {
        $this->actingAs($this->owner)->get(route('admin.invitations.create'))
            ->assertOk()
            ->assertSee('Branch Manager')
            ->assertSee('Central Hub Branch');
    }

    public function test_unauthorized_users_cannot_access_invitations(): void
    {
        // 1. Guest -> redirect to admin.login
        $guestResponse = $this->get(route('admin.invitations.index'));
        $guestResponse->assertRedirect(route('admin.login'));

        // 2. Customer -> 403 Forbidden
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $customerResponse = $this->actingAs($customer)->get(route('admin.invitations.index'));
        $customerResponse->assertForbidden();

        // 3. Seller -> 403 Forbidden
        $seller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $sellerResponse = $this->actingAs($seller)->get(route('admin.invitations.index'));
        $sellerResponse->assertForbidden();

        // 4. Staff -> 403 Forbidden
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $staff->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'role' => 'manager',
            'is_active' => true,
        ]);
        $staffResponse = $this->actingAs($staff)->get(route('admin.invitations.index'));
        $staffResponse->assertForbidden();
    }

    public function test_admin_can_issue_seller_invitation(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->post(route('admin.invitations.store'), [
            'role' => 'seller',
            'email' => 'cand.seller@example.test',
            'name' => 'Alice Seller',
        ]);

        $response->assertRedirect(route('admin.invitations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invitations', [
            'email' => 'cand.seller@example.test',
            'name' => 'Alice Seller',
            'role' => 'seller',
            'company_id' => $this->company->id,
            'branch_id' => null,
            'invited_by_user_id' => $this->admin->id,
            'accepted_at' => null,
            'revoked_at' => null,
        ]);

        $invitation = Invitation::where('email', 'cand.seller@example.test')->firstOrFail();
        $this->assertNotNull($invitation->token_hash);
        $this->assertSame(64, strlen($invitation->token_hash));

        Mail::assertSent(SellerInvitationMail::class, function (SellerInvitationMail $mail) use ($invitation) {
            return $mail->hasTo('cand.seller@example.test') &&
                   $mail->invitation->id === $invitation->id &&
                   ! empty($mail->rawToken);
        });
    }

    public function test_shop_owner_can_issue_branch_manager_invitation_with_valid_branch(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->owner)->post(route('admin.invitations.store'), [
            'role' => 'manager',
            'email' => 'cand.manager@example.test',
            'name' => 'Bob Manager',
            'branch_id' => $this->branch->id,
        ]);

        $response->assertRedirect(route('admin.invitations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invitations', [
            'email' => 'cand.manager@example.test',
            'name' => 'Bob Manager',
            'role' => 'manager',
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'invited_by_user_id' => $this->owner->id,
        ]);

        Mail::assertSent(BranchManagerInvitationMail::class, function (BranchManagerInvitationMail $mail) {
            return $mail->hasTo('cand.manager@example.test') && ! empty($mail->rawToken);
        });
    }

    public function test_branch_manager_invitation_requires_branch(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.invitations.store'), [
            'role' => 'manager',
            'email' => 'nobranch@example.test',
            'name' => 'No Branch',
            'branch_id' => '',
        ]);

        $response->assertSessionHasErrors(['branch_id']);
    }

    public function test_platform_admin_cannot_issue_employee_invitation(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post(route('admin.invitations.store'), [
            'role' => 'manager',
            'email' => 'unauthorized.manager@example.test',
            'branch_id' => $this->branch->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('invitations', ['email' => 'unauthorized.manager@example.test']);
        Mail::assertNothingSent();
    }

    public function test_seller_invitation_prohibits_branch(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.invitations.store'), [
            'role' => 'seller',
            'email' => 'hasbranch@example.test',
            'name' => 'Seller With Branch',
            'branch_id' => $this->branch->id,
        ]);

        $response->assertSessionHasErrors(['branch_id']);
    }

    public function test_cannot_invite_existing_registered_email(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);

        $response = $this->actingAs($this->admin)->post(route('admin.invitations.store'), [
            'role' => 'seller',
            'email' => 'existing@example.test',
            'name' => 'Existing User',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_cannot_invite_existing_email_with_different_case(): void
    {
        User::factory()->create(['email' => 'Existing.Case@example.test']);

        $response = $this->actingAs($this->admin)->post(route('admin.invitations.store'), [
            'role' => 'seller',
            'email' => 'existing.case@example.test',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_cannot_issue_duplicate_pending_invitation(): void
    {
        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $service->createInvitation([
            'role' => 'seller',
            'email' => 'pending@example.test',
            'name' => 'Pending Seller',
        ], $this->admin);

        $response = $this->actingAs($this->admin)->post(route('admin.invitations.store'), [
            'role' => 'seller',
            'email' => 'pending@example.test',
            'name' => 'Duplicate Pending',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_candidate_can_view_registration_form_with_valid_token(): void
    {
        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'seller',
            'email' => 'alice@example.test',
            'name' => 'Alice Green',
        ], $this->admin);

        $rawToken = $created['raw_token'];

        $response = $this->get(route('invitations.register', ['token' => $rawToken]));
        $response->assertOk();
        $response->assertSee('Official Seller Onboarding');
        $response->assertSee('alice@example.test');
        $response->assertSee('Marketplace Seller');
    }

    public function test_candidate_sees_invalid_view_for_invalid_expired_or_revoked_token(): void
    {
        // 1. Unknown token
        $response = $this->get(route('invitations.register', ['token' => 'invalid-token-12345']));
        $response->assertOk();
        $response->assertSee('Invalid Invitation Link');

        /** @var InvitationService $service */
        $service = app(InvitationService::class);

        // 2. Expired token
        Carbon::setTestNow(now()->subDays(5));
        $createdExpired = $service->createInvitation([
            'role' => 'seller',
            'email' => 'expired@example.test',
        ], $this->admin);
        Carbon::setTestNow(); // Back to present (token expired 2 days ago)

        $expiredResponse = $this->get(route('invitations.register', ['token' => $createdExpired['raw_token']]));
        $expiredResponse->assertOk();
        $expiredResponse->assertSee('Invitation Has Expired');

        // 3. Revoked token
        $createdRevoked = $service->createInvitation([
            'role' => 'seller',
            'email' => 'revoked@example.test',
        ], $this->admin);
        $service->revokeInvitation($createdRevoked['invitation'], $this->admin);

        $revokedResponse = $this->get(route('invitations.register', ['token' => $createdRevoked['raw_token']]));
        $revokedResponse->assertOk();
        $revokedResponse->assertSee('Invitation Has Been Revoked');
    }

    public function test_seller_candidate_completes_registration_and_is_redirected_to_otp_challenge(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'seller',
            'email' => 'seller.candidate@example.test',
            'name' => 'Sarah Seller',
        ], $this->admin);

        $rawToken = $created['raw_token'];

        $response = $this->post(route('invitations.complete', ['token' => $rawToken]), [
            'name' => 'Sarah Seller Updated',
            'password' => 'Password123!Safe',
            'password_confirmation' => 'Password123!Safe',
        ]);

        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();

        $user = User::where('email', 'seller.candidate@example.test')->firstOrFail();
        $this->assertSame('Sarah Seller Updated', $user->name);
        $this->assertSame('seller', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Password123!Safe', $user->password));

        $invitation = $created['invitation']->fresh();
        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($user->id, $invitation->accepted_user_id);

        // Verify OTP challenge was initiated with purpose seller_login
        $otp = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user, &$otp) {
            $otp = $mail->otp;

            return $mail->hasTo($user->email);
        });
        $this->assertNotNull($otp);

        // Now complete the OTP verification step
        $otpVerifyResponse = $this->post(route('login.otp.verify'), [
            'code' => $otp,
        ]);

        $otpVerifyResponse->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_branch_manager_candidate_completes_registration_and_is_redirected_to_admin_otp(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'manager',
            'email' => 'manager.candidate@example.test',
            'name' => 'Mark Manager',
            'branch_id' => $this->branch->id,
        ], $this->owner);

        $rawToken = $created['raw_token'];

        $response = $this->post(route('invitations.complete', ['token' => $rawToken]), [
            'name' => 'Mark Manager Assigned',
            'password' => 'ManagerSecret987!',
            'password_confirmation' => 'ManagerSecret987!',
        ]);

        $response->assertRedirect(route('admin.two-factor'));
        $this->assertGuest();

        $user = User::where('email', 'manager.candidate@example.test')->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertNull($user->email_verified_at);

        // Verify business membership was created for the branch
        $membership = BusinessMembership::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('manager', $membership->role);
        $this->assertSame($this->branch->id, $membership->branch_id);
        $this->assertSame($this->company->id, $membership->company_id);
        $this->assertTrue($membership->is_active);

        // Verify OTP challenge was initiated with purpose admin_login
        $otp = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user, &$otp) {
            $otp = $mail->otp;

            return $mail->hasTo($user->email);
        });
        $this->assertNotNull($otp);

        // Complete the Admin 2FA verification step
        $otpVerifyResponse = $this->post(route('admin.two-factor.store'), [
            'code' => $otp,
        ]);

        $otpVerifyResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_branch_manager_invitation_fails_closed_if_its_branch_is_deactivated(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'manager',
            'email' => 'orphan.manager@example.test',
            'branch_id' => $this->branch->id,
        ], $this->owner);

        $this->branch->update(['is_active' => false]);

        $registrationPage = $this->get(route('invitations.register', ['token' => $created['raw_token']]));
        $registrationPage->assertOk();
        $registrationPage->assertSee('Invitation No Longer Valid');

        $response = $this->post(route('invitations.complete', ['token' => $created['raw_token']]), [
            'name' => 'Orphan Manager',
            'password' => 'ManagerSecret987!',
            'password_confirmation' => 'ManagerSecret987!',
        ]);

        $response->assertSessionHasErrors(['invitation']);
        $this->assertDatabaseMissing('users', ['email' => 'orphan.manager@example.test']);
        $this->assertNull($created['invitation']->fresh()->accepted_at);
        $this->assertDatabaseHas('branches', [
            'id' => $this->branch->id,
            'is_active' => false,
        ]);
    }

    public function test_candidate_cannot_accept_after_the_company_becomes_inactive(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'manager',
            'email' => 'inactive.company.manager@example.test',
            'branch_id' => $this->branch->id,
        ], $this->owner);

        $this->company->update(['is_active' => false]);

        $this->post(route('invitations.complete', ['token' => $created['raw_token']]), [
            'name' => 'Inactive Company Manager',
            'password' => 'ManagerSecret987!',
            'password_confirmation' => 'ManagerSecret987!',
        ])->assertSessionHasErrors(['invitation']);

        $this->assertDatabaseMissing('users', ['email' => 'inactive.company.manager@example.test']);
        $this->assertNull($created['invitation']->fresh()->accepted_at);
    }

    public function test_consumed_invitation_cannot_create_a_second_account_on_retry(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'seller',
            'email' => 'one-use@example.test',
        ], $this->admin);
        $payload = [
            'name' => 'One Use Candidate',
            'password' => 'Password123!Safe',
            'password_confirmation' => 'Password123!Safe',
        ];

        $this->post(route('invitations.complete', ['token' => $created['raw_token']]), $payload)
            ->assertRedirect(route('login.otp.show'));
        $this->post(route('invitations.complete', ['token' => $created['raw_token']]), $payload)
            ->assertRedirect(route('invitations.register', ['token' => $created['raw_token']]));

        $this->assertSame(1, User::query()->where('email', 'one-use@example.test')->count());
        $this->assertSame(1, Invitation::query()->where('email', 'one-use@example.test')->whereNotNull('accepted_at')->count());
    }

    public function test_candidate_can_recover_from_an_interrupted_invitation_otp_session_by_logging_in_again(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'seller',
            'email' => 'recover@example.test',
        ], $this->admin);

        $this->post(route('invitations.complete', ['token' => $created['raw_token']]), [
            'name' => 'Recover Candidate',
            'password' => 'Password123!Safe',
            'password_confirmation' => 'Password123!Safe',
        ])->assertRedirect(route('login.otp.show'));

        $candidate = User::query()->where('email', 'recover@example.test')->firstOrFail();
        $this->post(route('login.otp.cancel'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $candidate->email,
            'password' => 'Password123!Safe',
        ])->assertRedirect(route('login.otp.show'));

        $this->assertGuest();
        $this->assertNull($candidate->fresh()->email_verified_at);
        $this->assertNotNull($created['invitation']->fresh()->accepted_at);
    }

    public function test_candidate_cannot_tamper_with_role_or_branch(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'seller',
            'email' => 'tamper@example.test',
            'name' => 'Tamper Candidate',
        ], $this->admin);

        // Candidate attempts to inject role=admin or branch_id=1
        $response = $this->post(route('invitations.complete', ['token' => $created['raw_token']]), [
            'name' => 'Tamper Candidate',
            'password' => 'Password123!Safe',
            'password_confirmation' => 'Password123!Safe',
            'role' => 'admin',
            'branch_id' => $this->branch->id,
        ]);

        $response->assertSessionHasErrors(['role', 'branch_id']);
        $this->assertDatabaseMissing('users', ['email' => 'tamper@example.test']);
    }

    public function test_admin_can_resend_invitation_invalidating_old_token(): void
    {
        Mail::fake();

        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'seller',
            'email' => 'resend@example.test',
            'name' => 'Resend Test',
        ], $this->admin);

        $oldToken = $created['raw_token'];
        $oldHash = $created['invitation']->token_hash;

        $response = $this->from(route('admin.invitations.index'))
            ->actingAs($this->admin)
            ->post(route('admin.invitations.resend', [
                'invitation' => $created['invitation']->id,
            ]));

        $response->assertRedirect(route('admin.invitations.index'));
        $response->assertSessionHas('success');

        $refreshed = $created['invitation']->fresh();
        $this->assertNotSame($oldHash, $refreshed->token_hash);

        // Old token must no longer work
        auth()->logout();
        $oldTokenResponse = $this->get(route('invitations.register', ['token' => $oldToken]));
        $oldTokenResponse->assertSee('Invalid Invitation Link');
        $this->post(route('invitations.complete', ['token' => $oldToken]), [
            'name' => 'Stale Token Candidate',
            'password' => 'Password123!Safe',
            'password_confirmation' => 'Password123!Safe',
        ])->assertRedirect(route('invitations.register', ['token' => $oldToken]));
        $this->assertDatabaseMissing('users', ['email' => 'resend@example.test']);

        // New mail was sent
        Mail::assertSent(SellerInvitationMail::class, 2);
    }

    public function test_admin_can_revoke_invitation(): void
    {
        /** @var InvitationService $service */
        $service = app(InvitationService::class);
        $created = $service->createInvitation([
            'role' => 'seller',
            'email' => 'revoke.me@example.test',
        ], $this->admin);

        $response = $this->from(route('admin.invitations.index'))
            ->actingAs($this->admin)
            ->delete(route('admin.invitations.revoke', [
                'invitation' => $created['invitation']->id,
            ]));

        $response->assertRedirect(route('admin.invitations.index'));
        $this->assertNotNull($created['invitation']->fresh()->revoked_at);

        auth()->logout();
        $candidateResponse = $this->get(route('invitations.register', ['token' => $created['raw_token']]));
        $candidateResponse->assertSee('Invitation Has Been Revoked');
    }
}
