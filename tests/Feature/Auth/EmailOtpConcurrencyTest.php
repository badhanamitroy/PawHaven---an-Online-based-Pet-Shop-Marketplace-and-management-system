<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailOtpConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_failed_delivery_cannot_delete_a_newer_resend_version(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $service = app(EmailOtpService::class);
        $oldPending = Mockery::mock(PendingMail::class);
        $newPending = Mockery::mock(PendingMail::class);
        $newCode = null;
        $calls = 0;

        Mail::shouldReceive('to')
            ->twice()
            ->with($user->email)
            ->andReturnUsing(function () use (&$calls, $oldPending, $newPending) {
                return ++$calls === 1 ? $oldPending : $newPending;
            });

        $oldPending->shouldReceive('send')
            ->once()
            ->with(Mockery::type(LoginOtpMail::class))
            ->andReturnUsing(function () use ($service, $user): never {
                $this->travel(61)->seconds();
                $service->generateAndSendOtp($user, 'customer_login', 'same-challenge');

                throw new RuntimeException('simulated delayed first delivery failure');
            });

        $newPending->shouldReceive('send')
            ->once()
            ->with(Mockery::type(LoginOtpMail::class))
            ->andReturnUsing(function (LoginOtpMail $mail) use (&$newCode): null {
                $newCode = $mail->otp;

                return null;
            });

        $oldResult = $service->generateAndSendOtp($user, 'customer_login', 'same-challenge');

        $this->assertFalse($oldResult['success']);
        $this->assertIsString($newCode);
        $this->assertSame('valid', $service->verifyOtp($user, $newCode, 'customer_login', 'same-challenge')['status']);
    }

    public function test_lock_contention_fails_closed_and_successful_code_is_consumed_once(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'customer']);
        $service = app(EmailOtpService::class);
        $this->assertTrue($service->generateAndSendOtp($user, 'customer_login', 'locked-challenge')['success']);

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code): bool {
            $code = $mail->otp;

            return true;
        });

        $lock = Cache::lock("email_otp:customer_login:{$user->id}:lock", 15);
        $this->assertTrue($lock->get());
        $this->assertSame('busy', $service->verifyOtp($user, $code, 'customer_login', 'locked-challenge')['status']);
        $lock->release();

        $this->assertSame('valid', $service->verifyOtp($user, $code, 'customer_login', 'locked-challenge')['status']);
        $this->assertSame('missing', $service->verifyOtp($user, $code, 'customer_login', 'locked-challenge')['status']);
    }

    public function test_fifth_wrong_attempt_consumes_the_code_before_a_later_correct_attempt(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'customer']);
        $service = app(EmailOtpService::class);
        $service->generateAndSendOtp($user, 'customer_login', 'attempt-limit');

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code): bool {
            $code = $mail->otp;

            return true;
        });

        for ($attempt = 1; $attempt < EmailOtpService::MAX_ATTEMPTS; $attempt++) {
            $this->assertSame('invalid', $service->verifyOtp($user, '000000', 'customer_login', 'attempt-limit')['status']);
        }

        $this->assertSame('max_attempts', $service->verifyOtp($user, '000000', 'customer_login', 'attempt-limit')['status']);
        $this->assertSame('missing', $service->verifyOtp($user, $code, 'customer_login', 'attempt-limit')['status']);
    }

    public function test_cache_lock_failure_does_not_report_a_successful_challenge(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $cache = Cache::getFacadeRoot();
        try {
            Cache::shouldReceive('lock')->once()->andThrow(new RuntimeException('cache unavailable'));

            $result = app(EmailOtpService::class)->generateAndSendOtp($user, 'customer_login', 'cache-failure');

            $this->assertFalse($result['success']);
            $this->assertStringNotContainsString('cache unavailable', $result['message']);
        } finally {
            Cache::swap($cache);
        }
    }

    public function test_cache_lock_failure_during_verification_never_returns_valid(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'customer']);
        $service = app(EmailOtpService::class);
        $service->generateAndSendOtp($user, 'customer_login', 'cache-verify-failure');

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code): bool {
            $code = $mail->otp;

            return true;
        });

        $cache = Cache::getFacadeRoot();
        try {
            Cache::shouldReceive('lock')->once()->andThrow(new RuntimeException('cache unavailable'));
            $result = $service->verifyOtp($user, $code, 'customer_login', 'cache-verify-failure');

            $this->assertSame('unavailable', $result['status']);
        } finally {
            Cache::swap($cache);
        }
    }
}
