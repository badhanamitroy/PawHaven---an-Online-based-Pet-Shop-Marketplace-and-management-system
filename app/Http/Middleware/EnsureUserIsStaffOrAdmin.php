<?php

namespace App\Http\Middleware;

use App\Services\BusinessAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaffOrAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isStaff())) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied. Staff or Administrator privileges required.');
        }

        if ($user->isStaff() && ! $user->isAdmin() && ! app(BusinessAccessService::class)->hasBusinessAccess($user)) {
            abort(Response::HTTP_FORBIDDEN, 'An active business membership is required for employee access.');
        }

        return $next($request);
    }
}
