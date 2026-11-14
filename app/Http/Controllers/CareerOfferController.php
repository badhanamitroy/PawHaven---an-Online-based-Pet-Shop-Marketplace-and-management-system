<?php

namespace App\Http\Controllers;

use App\Models\EmploymentOffer;
use App\Services\EmploymentOfferOtpService;
use App\Services\RecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CareerOfferController extends Controller
{
    private const SESSION_KEY = 'careers.offer_otp';

    public function show(string $token, RecruitmentService $recruitment): View
    {
        $offer = $recruitment->validateOffer($token);
        if (! $offer && $recruitment->offerWasAccepted($token)) {
            return view('careers.offer-complete');
        }

        return view('careers.offer', [
            'offer' => $offer,
            'invalid' => $offer === null,
            'token' => $offer ? $token : null,
        ]);
    }

    public function accept(Request $request, string $token, RecruitmentService $recruitment, EmploymentOfferOtpService $otp): RedirectResponse
    {
        $offer = $recruitment->validateOffer($token);
        if (! $offer) {
            return redirect()->route('careers.offers.show', ['token' => $token]);
        }

        $challenge = $otp->start($offer, $token);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'offer_id' => $offer->id,
            'token_hash' => $recruitment->tokenHash($token),
            'challenge_id' => $challenge->id,
        ]);

        return redirect()->route('careers.offers.verify');
    }

    public function resend(string $token, Request $request, RecruitmentService $recruitment, EmploymentOfferOtpService $otp): RedirectResponse
    {
        $offer = $recruitment->validateOffer($token);
        if (! $offer) {
            return redirect()->route('careers.offers.show', ['token' => $token]);
        }
        $challenge = $otp->start($offer, $token);
        $request->session()->put(self::SESSION_KEY, [
            'offer_id' => $offer->id,
            'token_hash' => $recruitment->tokenHash($token),
            'challenge_id' => $challenge->id,
        ]);

        return redirect()->route('careers.offers.verify')->with('status', 'A new verification code was sent.');
    }

    public function resendChallenge(Request $request, EmploymentOfferOtpService $otp): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $this->validSession($pending)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('careers.index')->withErrors(['offer' => 'Reopen your valid offer link to continue.']);
        }

        $challenge = $otp->resendFromSession((int) $pending['offer_id'], $pending['token_hash']);
        $pending['challenge_id'] = $challenge->id;
        $request->session()->put(self::SESSION_KEY, $pending);

        return redirect()->route('careers.offers.verify')->with('status', 'A new verification code was sent.');
    }

    public function decline(string $token, RecruitmentService $recruitment): RedirectResponse
    {
        $recruitment->declineOffer($token);

        return redirect()->route('careers.index')->with('success', 'Your response has been recorded. Thank you for letting PawHaven know.');
    }

    public function verifyPage(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $this->validSession($pending)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('careers.index')->withErrors(['offer' => 'Reopen your valid offer link to continue.']);
        }
        $offer = EmploymentOffer::query()->find($pending['offer_id']);
        if (! $offer) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('careers.index')->withErrors(['offer' => 'This offer is no longer available.']);
        }

        return view('careers.offer-otp', ['offer' => $offer]);
    }

    public function verify(Request $request, EmploymentOfferOtpService $otp): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D']]);
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $this->validSession($pending)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('careers.index')->withErrors(['offer' => 'Reopen your valid offer link to continue.']);
        }

        $otp->verifyAndCreateEmployee(
            (int) $pending['challenge_id'],
            (int) $pending['offer_id'],
            $pending['token_hash'],
            (string) $validated['code'],
        );
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('careers.complete');
    }

    public function complete(): View
    {
        return view('careers.complete');
    }

    private function validSession(mixed $pending): bool
    {
        return is_array($pending)
            && isset($pending['offer_id'], $pending['challenge_id'], $pending['token_hash'])
            && is_numeric($pending['offer_id'])
            && is_numeric($pending['challenge_id'])
            && is_string($pending['token_hash'])
            && preg_match('/\A[a-f0-9]{64}\z/', $pending['token_hash']) === 1;
    }
}
