<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\BusinessAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && app(BusinessAccessService::class)->hasBusinessAccess($user),
            Response::HTTP_FORBIDDEN,
            'An active company or branch assignment is required for shop operations.',
        );

        return $next($request);
    }
}
