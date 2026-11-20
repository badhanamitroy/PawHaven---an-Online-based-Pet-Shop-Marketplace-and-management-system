<?php

namespace App\Http\Middleware;

use App\Services\EmployeeSalesDeskService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeSeller
{
    /**
     * Restrict the Sales Desk to a staff account with one active seller membership.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $membership = app(EmployeeSalesDeskService::class)->membershipFor($request->user());
        $request->attributes->set('employee_sales_desk_membership', $membership);

        return $next($request);
    }
}
