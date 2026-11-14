<?php

namespace Tests\Feature\Careers;

use App\Mail\EmploymentOfferMail;
use App\Mail\EmploymentOfferOtpMail;
use App\Mail\PendingEmployeeApprovalMail;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\EmploymentOffer;
use App\Models\EmploymentOfferOtpChallenge;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Notifications\ResetPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CareersRecruitmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Company $company;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $this->branch = Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Central Hub Branch',
            'code' => 'CH-01',
            'is_active' => true,
        ]);
        $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->owner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $this->owner->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    #[DataProvider('employeePositions')]
    public function test_public_application_offer_otp_and_owner_approval_create_the_correct_employee(string $position, string $expectedRole): void
    {
        Mail::fake();
        Notification::fake();
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData($position))
            ->assertRedirect();
        $posting = JobPosting::query()->firstOrFail();

        Auth::logout();
        $this->get(route('careers.index'))->assertOk()->assertSee($posting->title);
        $this->post(route('careers.apply', $posting->slug), [
            'full_name' => 'Taylor Candidate',
            'email' => 'taylor.'.($position === 'manager' ? 'manager' : 'seller').'@example.test',
            'phone' => '555-0100',
            'preferred_branch_id' => $this->branch->id,
            'motivation' => 'I would like to work with PawHaven.',
        ])->assertRedirect(route('careers.show', $posting->slug));

        $application = JobApplication::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.careers.applications.review', $application), [
            'status' => 'selected',
            'internal_notes' => 'Reviewed by recruitment.',
        ])->assertRedirect();
        $this->post(route('admin.careers.applications.offers.store', $application), [
            'branch_id' => $this->branch->id,
            'employment_terms' => 'The role and assignment are as stated in this offer.',
            'acceptance_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $offer = EmploymentOffer::query()->firstOrFail();
        $rawToken = null;
        Mail::assertSent(EmploymentOfferMail::class, function (EmploymentOfferMail $mail) use (&$rawToken): bool {
            $rawToken = $mail->rawToken;

            return $mail->hasTo('taylor.'.(str_contains($mail->offer->candidate_email, 'manager') ? 'manager' : 'seller').'@example.test');
        });
        $this->assertNotNull($rawToken);
        $this->assertSame(64, strlen($offer->token_hash));
        $this->assertStringNotContainsString($rawToken, $offer->token_hash);

        Auth::logout();
        $this->get(route('careers.offers.show', $rawToken))->assertOk()->assertSee('Employment offer');
        $this->post(route('careers.offers.accept', $rawToken))->assertRedirect(route('careers.offers.verify'));
        $code = null;
        Mail::assertSent(EmploymentOfferOtpMail::class, function (EmploymentOfferOtpMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->assertNotNull($code);
        $this->assertDatabaseHas('employment_offer_otp_challenges', ['offer_id' => $offer->id, 'consumed_at' => null]);

        $this->post(route('careers.offers.verify.submit'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, EmploymentOfferOtpChallenge::query()->firstOrFail()->attempts);
        $this->assertDatabaseMissing('users', ['email' => $offer->candidate_email]);

        $this->travel(61)->seconds();
        $this->post(route('careers.offers.verify.resend'))->assertRedirect(route('careers.offers.verify'));
        $this->assertSame(2, Mail::sent(EmploymentOfferOtpMail::class)->count());
        $newCode = Mail::sent(EmploymentOfferOtpMail::class)->last()->code;
        $this->post(route('careers.offers.verify.submit'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame(1, EmploymentOfferOtpChallenge::query()->where('active_offer_id', $offer->id)->firstOrFail()->attempts);

        $this->post(route('careers.offers.verify.submit'), ['code' => $newCode])->assertRedirect(route('careers.complete'));
        $employee = User::query()->where('email', $offer->candidate_email)->firstOrFail();
        $this->get(route('careers.offers.show', $rawToken))->assertOk()->assertSee('This offer has already been accepted');
        $consumedChallenge = EmploymentOfferOtpChallenge::query()->where('offer_id', $offer->id)->whereNotNull('consumed_at')->firstOrFail();
        $replayed = app(\App\Services\EmploymentOfferOtpService::class)->verifyAndCreateEmployee(
            (int) $consumedChallenge->id,
            (int) $offer->id,
            (string) $offer->fresh()->token_hash,
            '000000',
        );
        $this->assertSame($employee->id, $replayed->id);
        $this->assertSame(1, User::query()->where('email', $offer->candidate_email)->count());
        $membership = BusinessMembership::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('staff', $employee->role);
        $this->assertTrue($employee->hasVerifiedEmail());
        $this->assertSame($expectedRole, $membership->role);
        $this->assertSame($this->company->id, $membership->company_id);
        $this->assertSame($this->branch->id, $membership->branch_id);
        $this->assertFalse($membership->is_active);
        $this->assertSame('accepted', $offer->fresh()->status);
        $this->assertSame('awaiting_owner_approval', $application->fresh()->status);
        $this->assertFalse(Auth::check(), 'Offer OTP must not authenticate the candidate.');
        Mail::assertSent(PendingEmployeeApprovalMail::class);

        $this->actingAs($employee)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.careers.approvals.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('admin.careers.approvals.index'))->assertOk()->assertSee($employee->email);
        $this->post(route('admin.careers.approvals.approve', $offer), [])->assertRedirect();

        $this->assertTrue($membership->fresh()->is_active);
        $this->assertSame('hired', $application->fresh()->status);
        Notification::assertSentTo($employee, ResetPassword::class);

        Auth::logout();
        $this->actingAs($employee)->get(route('admin.dashboard'))->assertOk();
        $this->travelBack();
    }

    public static function employeePositions(): array
    {
        return [
            'employee seller' => ['seller', 'seller'],
            'branch manager' => ['manager', 'manager'],
        ];
    }

    public function test_public_vacancies_hide_drafts_closed_expired_and_inactive_company_roles(): void
    {
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('seller', 'Published Role'));
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('seller', 'Draft Role', 'draft'));
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('manager', 'Closed Role', 'closed'));
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('manager', 'Expired Role', 'published', today()->subDay()->toDateString()));

        $this->get(route('careers.index'))->assertOk()->assertSee('Published Role')
            ->assertDontSee('Draft Role')->assertDontSee('Closed Role')->assertDontSee('Expired Role');
    }

    public function test_homepage_careers_navigation_and_admin_recruitment_pages_are_connected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('seller'));
        $posting = JobPosting::query()->firstOrFail();
        $application = JobApplication::query()->create([
            'job_posting_id' => $posting->id,
            'full_name' => 'Private Candidate',
            'email' => 'private-candidate@example.test',
            'motivation' => 'Public motivation text.',
            'internal_notes' => 'Confidential reviewer note.',
            'status' => 'submitted',
            'active_submission_key' => hash('sha256', 'private-candidate'),
            'submitted_at' => now(),
        ]);

        $this->get(route('admin.careers.vacancies.index'))->assertOk()->assertSee($posting->title);
        $this->get(route('admin.careers.vacancies.create'))->assertOk()->assertSee('Branch options');
        $this->get(route('admin.careers.applications.index'))->assertOk()->assertSee($application->email);
        $this->get(route('admin.careers.applications.show', $application))->assertOk()->assertSee('Confidential reviewer note.');

        Auth::logout();
        $this->get(route('public.home'))->assertOk()->assertSee(route('careers.index'));
        $this->get(route('careers.show', $posting->slug))->assertOk()
            ->assertDontSee('Confidential reviewer note.')
            ->assertDontSee($application->email);

        $this->actingAs($this->owner)->get(route('admin.careers.vacancies.index'))->assertForbidden();
    }

    public function test_duplicate_application_is_blocked_and_candidate_cannot_choose_final_role_or_branch(): void
    {
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('seller'));
        $posting = JobPosting::query()->firstOrFail();
        $data = [
            'full_name' => 'Candidate', 'email' => 'candidate@example.test', 'motivation' => 'I am interested.',
            'preferred_branch_id' => $this->branch->id, 'role' => 'manager', 'branch_id' => 999999,
        ];
        Auth::logout();
        $this->post(route('careers.apply', $posting->slug), $data)->assertRedirect();
        $this->post(route('careers.apply', $posting->slug), $data)->assertSessionHasErrors('email');
        $application = JobApplication::query()->firstOrFail();
        $this->assertSame('submitted', $application->status);
        $this->assertDatabaseCount('job_applications', 1);
        $this->assertArrayNotHasKey('position_type', $application->getAttributes());
    }

    public function test_offer_token_rotation_and_revocation_invalidate_previous_links(): void
    {
        Mail::fake();
        $offer = $this->issueOfferThroughAdmin();
        $oldToken = $this->lastOfferToken();
        $this->post(route('admin.careers.offers.resend', $offer))->assertRedirect();
        $newToken = $this->lastOfferToken();
        $this->assertNotSame($oldToken, $newToken);
        $this->get(route('careers.offers.show', $oldToken))->assertSee('This offer link is unavailable');
        $this->get(route('careers.offers.show', $newToken))->assertSee('Employment offer');
        $this->delete(route('admin.careers.offers.revoke', $offer))->assertRedirect();
        $this->get(route('careers.offers.show', $newToken))->assertSee('This offer link is unavailable');
    }

    public function test_expired_offer_otp_can_be_restarted_without_creating_a_user_early(): void
    {
        Mail::fake();
        $offer = $this->issueOfferThroughAdmin();
        $token = $this->lastOfferToken();
        Auth::logout();
        $this->post(route('careers.offers.accept', $token))->assertRedirect(route('careers.offers.verify'));
        $firstCode = Mail::sent(EmploymentOfferOtpMail::class)->last()->code;

        $this->travel(11)->minutes();
        $this->post(route('careers.offers.verify.submit'), ['code' => $firstCode])->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('users', ['email' => $offer->candidate_email]);
        $this->assertNotNull(EmploymentOfferOtpChallenge::query()->firstOrFail()->invalidated_at);

        $this->post(route('careers.offers.accept', $token))->assertRedirect(route('careers.offers.verify'));
        $newCode = Mail::sent(EmploymentOfferOtpMail::class)->last()->code;
        $this->post(route('careers.offers.verify.submit'), ['code' => $newCode])->assertRedirect(route('careers.complete'));
        $this->assertDatabaseHas('users', ['email' => $offer->candidate_email, 'role' => 'staff']);
        $this->assertSame(1, User::query()->where('email', $offer->candidate_email)->count());
        $this->travelBack();
    }

    public function test_otp_delivery_failure_invalidates_the_challenge_and_does_not_consume_the_offer(): void
    {
        Mail::fake();
        $offer = $this->issueOfferThroughAdmin();
        $token = $this->lastOfferToken();
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('mail transport failure'));
        Mail::shouldReceive('to')->once()->with($offer->candidate_email)->andReturn($mailer);

        try {
            app(\App\Services\EmploymentOfferOtpService::class)->start($offer, $token);
            $this->fail('A mail transport failure should not produce a usable OTP challenge.');
        } catch (\Illuminate\Validation\ValidationException) {
            $challenge = EmploymentOfferOtpChallenge::query()->firstOrFail();
            $this->assertNull($challenge->active_offer_id);
            $this->assertNotNull($challenge->invalidated_at);
            $this->assertSame('offered', $offer->fresh()->status);
            $this->assertDatabaseMissing('users', ['email' => $offer->candidate_email]);
        }
    }

    public function test_private_cv_is_only_available_through_admin_download_route(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('seller'));
        $posting = JobPosting::query()->firstOrFail();
        Auth::logout();
        $response = $this->post(route('careers.apply', $posting->slug), [
            'full_name' => 'CV Candidate', 'email' => 'cv@example.test', 'motivation' => 'Interested.',
            'cv' => \Illuminate\Http\UploadedFile::fake()->create('resume.pdf', 50, 'application/pdf'),
        ]);
        $response->assertRedirect();
        $application = JobApplication::query()->firstOrFail();
        Storage::disk('local')->assertExists($application->cv_path);
        $this->get(route('admin.careers.applications.cv', $application))->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin)->get(route('admin.careers.applications.cv', $application))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    private function vacancyData(string $position, string $title = 'Sales Associate', string $status = 'published', ?string $deadline = null): array
    {
        return [
            'title' => $title,
            'position_type' => $position,
            'summary' => 'Join our PawHaven team.',
            'description' => 'A role supporting PawHaven customers and branches.',
            'responsibilities' => 'Support daily operations.',
            'requirements' => 'Relevant experience is helpful.',
            'employment_type' => 'Full time',
            'openings' => 1,
            'application_deadline' => $deadline,
            'status' => $status,
            'branch_ids' => [$this->branch->id],
        ];
    }

    private function issueOfferThroughAdmin(): EmploymentOffer
    {
        $this->actingAs($this->admin)->post(route('admin.careers.vacancies.store'), $this->vacancyData('seller'));
        $posting = JobPosting::query()->firstOrFail();
        $application = JobApplication::query()->create([
            'job_posting_id' => $posting->id,
            'email' => 'offer-candidate@example.test',
            'full_name' => 'Offer Candidate',
            'motivation' => 'Interested.',
            'status' => 'submitted',
            'active_submission_key' => hash('sha256', 'existing'),
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->admin)->post(route('admin.careers.applications.review', $application), ['status' => 'selected'])->assertRedirect();
        $this->post(route('admin.careers.applications.offers.store', $application), [
            'branch_id' => $this->branch->id,
            'employment_terms' => 'Terms approved by PawHaven.',
            'acceptance_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        return EmploymentOffer::query()->firstOrFail();
    }

    private function lastOfferToken(): string
    {
        $token = null;
        Mail::assertSent(EmploymentOfferMail::class, function (EmploymentOfferMail $mail) use (&$token): bool {
            $token = $mail->rawToken;

            return true;
        });

        return (string) $token;
    }
}
