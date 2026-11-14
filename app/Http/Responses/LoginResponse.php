<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        if ($user instanceof User) {
            $destination = match (true) {
                $user->isAdmin(), $user->isStaff() => route('admin.dashboard'),
                $user->isSeller() => route('seller.dashboard'),
                $user->isCustomer() => route('dashboard'),
                default => abort(403, 'Access denied. A recognized account role is required.'),
            };

            $intended = $this->authorizedIntendedUrl($request, $user);
            if ($intended !== null) {
                $request->session()->forget('url.intended');

                return redirect()->to($intended);
            }

            $request->session()->forget('url.intended');

            return redirect()->to($destination);
        }

        return redirect()->route('login');
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
