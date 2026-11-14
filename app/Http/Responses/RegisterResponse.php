<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function __construct(private EmailOtpService $otpService) {}

    public function toResponse($request): Response
    {
        if (! $request instanceof Request || ! ($request->user() instanceof User)) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->regenerate();

        $challengeId = bin2hex(random_bytes(32));
        $request->session()->put('auth.email_otp', [
            'user_id' => $user->getKey(),
            'purpose' => 'customer_login',
            'challenge_id' => $challengeId,
            'remember' => false,
            'requires_totp' => false,
        ]);

        $result = $this->otpService->generateAndSendOtp($user, 'customer_login', $challengeId);
        if (! $result['success']) {
            $request->session()->forget('auth.email_otp');

            return redirect()->route('login')
                ->withErrors(['email' => $result['message']]);
        }

        return $request->wantsJson()
            ? new JsonResponse(['email_otp' => true], Response::HTTP_ACCEPTED)
            : redirect()->route('login.otp.show');
    }
}
