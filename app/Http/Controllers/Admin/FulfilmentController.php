<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentAction;
use App\Enums\FulfilmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Services\BusinessAccessService;
use App\Services\FulfilmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FulfilmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);
        $membership = app(BusinessAccessService::class)->activeMemberships($request->user())->first();
        abort_unless($membership && in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true), 403);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(FulfilmentStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($membership->role === BusinessAccessService::MANAGER) {
            if (! empty($filters['branch_id']) && (int) $filters['branch_id'] !== (int) $membership->branch_id) {
                abort(403, 'Branch Managers can view fulfilment only for their assigned branch.');
            }
            $filters['branch_id'] = (int) $membership->branch_id;
        } elseif (! empty($filters['branch_id']) && ! Branch::query()
            ->where('company_id', $membership->company_id)->whereKey($filters['branch_id'])->exists()) {
            abort(403, 'The selected branch is outside your company.');
        }

        $base = Order::query()->whereNull('seller_id')->whereNull('pet_listing_id');
        $counts = [];
        foreach (FulfilmentStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('fulfilment_status', $status)->count();
        }

        $query = (clone $base)->with(['branch', 'user']);
        if (! empty($filters['status'])) {
            $query->where('fulfilment_status', $filters['status']);
        }
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($filters['to'].' +1 day')).' 00:00:00');
        }

        $orders = $query->latest()->paginate(20)->withQueryString();
        $branches = $membership->role === BusinessAccessService::OWNER
            ? Branch::query()->where('company_id', $membership->company_id)->orderBy('name')->get()
            : collect();

        return view('admin.fulfilment.index', compact('orders', 'counts', 'filters', 'branches', 'membership'));
    }

    public function transition(Request $request, Order $order, FulfilmentService $fulfilment): RedirectResponse
    {
        Gate::authorize('update', $order);
        $validated = $request->validate([
            'action' => ['required', Rule::enum(FulfilmentAction::class)],
            'submission_key' => ['required', 'uuid'],
            'delivery_method' => ['nullable', Rule::enum(\App\Enums\DeliveryMethod::class)],
            'delivery_provider' => ['nullable', 'string', 'max:120'],
            'tracking_reference' => ['nullable', 'string', 'max:120'],
            'order_item_id' => ['nullable', 'integer', 'min:1'],
            'items_verified' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $event = $fulfilment->transition(
            $order,
            $request->user(),
            FulfilmentAction::from($validated['action']),
            $validated,
        );

        return redirect()->route('admin.orders.show', $event->order_id)
            ->with('success', 'Fulfilment action recorded.');
    }
}
