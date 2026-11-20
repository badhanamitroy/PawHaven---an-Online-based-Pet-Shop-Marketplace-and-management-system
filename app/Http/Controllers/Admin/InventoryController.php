<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\InventoryMovementType;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\BusinessAccessService;
use App\Services\InventoryReportingService;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);
        abort_unless(app(\App\Services\BusinessAccessService::class)
            ->activeMemberships($request->user())->contains(fn ($membership): bool =>
                in_array($membership->role, ['owner', 'manager'], true)
            ), 403);

        $query = Product::with(['category', 'branch']);

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            if ($filter === 'low_stock') {
                $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->where('stock_quantity', '>', 0);
            } elseif ($filter === 'out_of_stock') {
                $query->where('stock_quantity', '<=', 0);
            } elseif ($filter === 'in_stock') {
                $query->whereColumn('stock_quantity', '>', 'low_stock_threshold');
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('stock_quantity', 'asc')->paginate(20)->withQueryString();

        $lowStockTotal = Product::lowStock()->count();
        $outOfStockTotal = Product::where('stock_quantity', '<=', 0)->count();
        $totalItemsInStock = Product::sum('stock_quantity');

        return view('admin.inventory.index', compact('products', 'lowStockTotal', 'outOfStockTotal', 'totalItemsInStock'));
    }

    public function update(Request $request, Product $product, InventoryMovementService $inventory): RedirectResponse
    {
        abort_unless(app(\App\Services\BusinessAccessService::class)
            ->canManageInventory($request->user(), $product->company_id), 403);
        abort_unless(app(\App\Services\BusinessAccessService::class)
            ->canAccessRecord($request->user(), $product->company_id, $product->branch_id), 403);

        $validated = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'stock_adjustment_reason' => ['required', 'string', 'max:1000'],
            'adjustment_key' => ['required', 'uuid'],
        ]);

        $oldStock = $product->stock_quantity;
        DB::transaction(function () use ($request, $product, $validated, $inventory): void {
            $locked = Product::query()->withoutGlobalScopes()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $inventory->adjustToQuantity(
                $locked,
                $request->user(),
                (int) $validated['stock_quantity'],
                $validated['stock_adjustment_reason'],
                $validated['adjustment_key'],
            );
            $locked->forceFill(['low_stock_threshold' => (int) $validated['low_stock_threshold']])->save();
        });

        AdminAuditLog::record(
            'inventory.adjust',
            "Adjusted stock for '{$product->name}' (SKU: {$product->sku}) from {$oldStock} to {$validated['stock_quantity']}.",
            [
                'product_id' => $product->id,
                'old_stock' => $oldStock,
                'new_stock' => (int) $validated['stock_quantity'],
                'reason' => $validated['stock_adjustment_reason'],
            ]
        );

        return redirect()->route('admin.inventory.index')->with('success', "Stock for '{$product->name}' updated to {$validated['stock_quantity']}.");
    }

    public function movements(Request $request, InventoryReportingService $reporting): View
    {
        $membership = app(BusinessAccessService::class)->activeMemberships($request->user())->first();
        abort_unless($membership && in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'movement_type' => ['nullable', Rule::enum(InventoryMovementType::class)],
        ]);

        if ($membership->role === BusinessAccessService::MANAGER) {
            if (! empty($filters['branch_id']) && (int) $filters['branch_id'] !== (int) $membership->branch_id) {
                abort(403, 'Branch Managers can view inventory activity only for their assigned branch.');
            }
            $filters['branch_id'] = (int) $membership->branch_id;
        } elseif (! empty($filters['branch_id']) && ! Branch::query()
            ->where('company_id', $membership->company_id)->whereKey($filters['branch_id'])->exists()) {
            abort(403, 'The requested branch is outside your company.');
        }

        if (! empty($filters['product_id']) && ! Product::query()->withoutGlobalScopes()
            ->where('company_id', $membership->company_id)->whereKey($filters['product_id'])->exists()) {
            abort(403, 'The requested product is outside your company.');
        }
        if ($membership->role === BusinessAccessService::MANAGER && ! empty($filters['product_id'])
            && ! Product::query()->withoutGlobalScopes()->where('company_id', $membership->company_id)
                ->whereKey($filters['product_id'])->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $membership->branch_id))->exists()) {
            abort(403, 'The requested product is outside your assigned branch and shared inventory scope.');
        }

        $movements = $reporting->movementQuery($membership, $filters)->paginate(30)->withQueryString();
        $summary = $reporting->summary($membership, $filters);
        $products = Product::query()->withoutGlobalScopes()->where('company_id', $membership->company_id)
            ->when($membership->role === BusinessAccessService::MANAGER, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $membership->branch_id)))
            ->orderBy('name')->get(['id', 'name', 'sku']);
        $branches = $reporting->branchOptions($membership);

        return view('admin.inventory.movements', compact('movements', 'summary', 'products', 'branches', 'filters', 'membership'));
    }
}
