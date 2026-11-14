<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::customers()->withCount('orders');

        if ($request->user()?->isStaff()) {
            $query->whereHas('orders');
        } else {
            $query->withCount('petListings');
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $customers = $query->latest()->paginate(20)->withQueryString();

        // Calculate total spend per customer
        $customerIds = $customers->pluck('id')->toArray();
        $spendTotals = Order::whereIn('user_id', $customerIds)
            ->where('status', OrderStatus::Delivered)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(total_minor) as total_spent')
            ->pluck('total_spent', 'user_id');

        $customerCountQuery = User::customers();
        if ($request->user()?->isStaff()) {
            $customerCountQuery->whereHas('orders');
        }
        $totalCustomersCount = $customerCountQuery->count();

        return view('admin.customers.index', compact('customers', 'spendTotals', 'totalCustomersCount'));
    }
}
