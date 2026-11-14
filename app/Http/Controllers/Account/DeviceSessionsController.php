<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceSession;
use App\Services\UserDeviceSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeviceSessionsController extends Controller
{
    public function index(Request $request): View
    {
        $devices = $request->user()->deviceSessions()
            ->latest('last_activity_at')
            ->limit(50)
            ->get();

        return view('settings.devices', [
            'devices' => $devices,
            'currentDeviceId' => (int) $request->session()->get(UserDeviceSessionService::SESSION_KEY, 0),
        ]);
    }

    public function revoke(
        Request $request,
        int $deviceSession,
        UserDeviceSessionService $deviceSessions,
    ): RedirectResponse {
        $device = $request->user()->deviceSessions()->findOrFail($deviceSession);
        $deviceSessions->revoke($device);

        if ((int) $request->session()->get(UserDeviceSessionService::SESSION_KEY) === $device->getKey()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $deviceSessions->queueExpiredCookie();

            return redirect()->route('public.home');
        }

        return back()->with('success', 'That device has been signed out.');
    }

    public function revokeOthers(Request $request, UserDeviceSessionService $deviceSessions): RedirectResponse
    {
        $currentDeviceId = $request->session()->get(UserDeviceSessionService::SESSION_KEY);
        $deviceSessions->revokeAllForUser((int) $request->user()->getKey(), $currentDeviceId ? (int) $currentDeviceId : null);

        return back()->with('success', 'All other devices have been signed out.');
    }

    public function revokeAll(Request $request, UserDeviceSessionService $deviceSessions): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:LOG OUT ALL DEVICES'],
        ]);

        $deviceSessions->revokeAllForUser((int) $request->user()->getKey());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $deviceSessions->queueExpiredCookie();

        return redirect()->route('public.home');
    }
}
