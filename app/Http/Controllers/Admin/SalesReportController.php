<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Services\BusinessAccessService;
use App\Services\SalesReportingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function mySales(Request $request, SalesReportingService $reports): View
    {
        $user = $request->user();
        $membership = $reports->membershipFor($user, BusinessAccessService::EMPLOYEE_SELLER);
        $filters = $this->validatedFilters($request);
        $query = $reports->applyFilters($reports->employeeOrders($user, $membership), $filters);
        $orders = (clone $query)->with(['user:id,name,email', 'branch:id,name', 'paymentTransactions'])
            ->latest()->paginate(20)->withQueryString();
        $summary = $reports->summary(clone $query);

        return view('admin.sales-reports.my', compact('orders', 'summary', 'filters'));
    }

    public function showMyOrder(Request $request, int $order, SalesReportingService $reports): View
    {
        $user = $request->user();
        $membership = $reports->membershipFor($user, BusinessAccessService::EMPLOYEE_SELLER);
        $sale = $reports->employeeOrders($user, $membership)
            ->whereKey($order)
            ->with(['items', 'user:id,name,email', 'branch:id,name', 'paymentTransactions'])
            ->firstOrFail();

        return view('admin.sales-reports.employee-order', compact('sale'));
    }

    public function branchSales(Request $request, SalesReportingService $reports): View
    {
        $membership = $reports->membershipFor($request->user(), BusinessAccessService::MANAGER);
        $filters = $this->validatedFilters($request);
        $query = $reports->applyFilters($reports->branchOrders($membership), $filters);
        $orders = (clone $query)->with(['employeeSeller:id,name,role', 'user:id,name,email', 'branch:id,name', 'paymentTransactions'])
            ->latest()->paginate(20)->withQueryString();
        $summary = $reports->summary(clone $query);
        $channels = $reports->channelBreakdown(clone $query);
        $employees = $reports->employeeBreakdown(clone $query);
        $attribution = $reports->attributionByChannel(clone $query);

        return view('admin.sales-reports.branch', compact('filters', 'orders', 'summary', 'channels', 'employees', 'attribution', 'membership'));
    }

    public function businessSales(Request $request, SalesReportingService $reports): View
    {
        $membership = $reports->membershipFor($request->user(), BusinessAccessService::OWNER);
        $filters = $this->validatedFilters($request);
        $query = $reports->applyFilters($reports->companyOrders($membership), $filters);
        $orders = (clone $query)->with(['employeeSeller:id,name,role', 'user:id,name,email', 'branch:id,name', 'paymentTransactions'])
            ->latest()->paginate(20)->withQueryString();
        $summary = $reports->summary(clone $query);
        $branches = $reports->branchBreakdown(clone $query);
        $channels = $reports->channelBreakdown(clone $query);
        $employees = $reports->employeeBreakdown(clone $query);
        $attribution = $reports->attributionByChannel(clone $query);

        return view('admin.sales-reports.business', compact('filters', 'orders', 'summary', 'branches', 'channels', 'employees', 'attribution'));
    }

    /** @return array<string, string|null> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'transaction_status' => ['nullable', Rule::enum(PaymentTransactionStatus::class)],
            'channel' => ['nullable', Rule::enum(SalesChannel::class)],
        ]);
    }
}
