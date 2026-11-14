<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->noContent();
        }

        $user = $request->user();
        if (! $user instanceof User) {
            $request->session()->forget('url.intended');

            return redirect()->route('login');
        }

        $destination = match (true) {
            $user->isAdmin(), $user->isStaff() => route('admin.dashboard'),
            $user->isSeller() => route('seller.dashboard'),
            $user->isCustomer() => route('dashboard'),
            default => abort(403, 'Access denied. A recognized account role is required.'),
        };

        $intended = $this->authorizedIntendedUrl($request, $user);
        $request->session()->forget('url.intended');

        return redirect()->to($intended ?? $destination);
    }

    private function authorizedIntendedUrl(Request $request, User $user): ?string
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $url = parse_url($intended);
        $path = $url['path'] ?? null;
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $expectedHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (isset($url['host']) && strtolower($url['host']) !== strtolower((string) $expectedHost)) {
            return null;
        }

        if ($user->isCustomer() && ($this->pathIs($path, '/admin') || $this->pathIs($path, '/seller'))) {
            return null;
        }

        if ($user->isSeller() && $this->pathIs($path, '/admin')) {
            return null;
        }

        if (($user->isAdmin() || $user->isStaff()) && $this->pathIs($path, '/seller')) {
            return null;
        }

        if ($user->isStaff() && preg_match('#^/admin/(brand|homepage|marketplace/(moderation|categories)|users|audit-logs|system|settings)(/|$)#', $path)) {
            return null;
        }

        return $intended;
    }

    private function pathIs(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
