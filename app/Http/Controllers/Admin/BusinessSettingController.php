<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BusinessAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessSettingController extends Controller
{
    public function shop(): View
    {
        $this->authorizeShopOwner();

        return view('admin.settings.shop', [
            'settings' => Settings::all(),
        ]);
    }

    public function updateShop(Request $request): RedirectResponse
    {
        $this->authorizeShopOwner();

        $validated = $request->validate([
            'currency' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'free_shipping_threshold' => ['required', 'numeric', 'min:0'],
            'cod_enabled' => ['nullable', 'boolean'],
            'store_address' => ['nullable', 'string', 'max:500'],
            'customer_support_phone' => ['nullable', 'string', 'max:50'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $deliveryFeeMinor = (int) round(((float) $validated['delivery_fee']) * 100);
        $thresholdMinor = (int) round(((float) $validated['free_shipping_threshold']) * 100);

        Settings::setMany([
            'shop.currency' => strtoupper($validated['currency']),
            'shop.currency_symbol' => $validated['currency_symbol'],
            'shop.delivery_fee_minor' => (string) $deliveryFeeMinor,
            'shop.free_shipping_threshold_minor' => (string) $thresholdMinor,
            'shop.cod_enabled' => $request->boolean('cod_enabled') ? 'true' : 'false',
            'shop.store_address' => $validated['store_address'] ?? '',
            'shop.customer_support_phone' => $validated['customer_support_phone'] ?? '',
            'shop.tax_percentage' => (string) ($validated['tax_percentage'] ?? '0'),
        ], 'shop');

        AdminAuditLog::record(
            'settings.shop.update',
            'Updated shop and delivery commerce settings.',
            [
                'currency' => $validated['currency'],
                'delivery_fee_minor' => $deliveryFeeMinor,
                'cod_enabled' => $request->boolean('cod_enabled'),
            ]
        );

        return redirect()->route('admin.settings.shop')->with('success', 'Shop & Delivery settings saved successfully.');
    }

    public function features(): View
    {
        $branches = Branch::orderBy('name')->get();

        return view('admin.settings.features', [
            'settings' => Settings::all(),
            'branches' => $branches,
        ]);
    }

    public function updateFeatures(Request $request): RedirectResponse
    {
        $multiStaff = $request->boolean('multi_staff_enabled');
        $multiBranch = $request->boolean('multi_branch_enabled');

        Settings::setMany([
            'features.multi_staff_enabled' => $multiStaff ? 'true' : 'false',
            'features.multi_branch_enabled' => $multiBranch ? 'true' : 'false',
        ], 'features');

        AdminAuditLog::record(
            'settings.features.update',
            "Updated feature flags: multi-staff={$multiStaff}, multi-branch={$multiBranch}.",
            [
                'multi_staff_enabled' => $multiStaff,
                'multi_branch_enabled' => $multiBranch,
            ]
        );

        return redirect()->route('admin.settings.features')->with('success', 'Feature flags updated successfully.');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $access = app(BusinessAccessService::class);
        $company = $access->installationCompany();
        abort_unless($access->canManageCompany($request->user(), (int) $company->id), 403);

        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch = DB::transaction(function () use ($company, $request, $validated): Branch {
            Company::query()->withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            return Branch::create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'address' => $validated['address'],
                'city' => $validated['city'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        });

        AdminAuditLog::record(
            'branch.create',
            "Created branch '{$branch->name}' ({$branch->code}).",
            ['company_id' => $branch->company_id, 'branch_code' => $branch->code]
        );

        return redirect()->route('admin.settings.features')->with('success', "Branch '{$branch->name}' created successfully.");
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        $company = app(BusinessAccessService::class)->installationCompany((int) $branch->company_id);
        abort_unless(app(BusinessAccessService::class)->canManageCompany(request()->user(), (int) $company->id), 403);

        if ($branch->memberships()->exists()
            || Product::query()->where('branch_id', $branch->id)->exists()
            || Order::query()->where('branch_id', $branch->id)->exists()
            || Invitation::query()->where('branch_id', $branch->id)->exists()
            || AdminAuditLog::query()->where('branch_id', $branch->id)->exists()) {
            return redirect()->route('admin.settings.features')
                ->with('error', "Branch '{$branch->name}' is assigned to staff or business records and cannot be deleted.");
        }

        $name = $branch->name;
        DB::transaction(function () use ($branch, $company): void {
            Company::query()->withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $lockedBranch = Branch::query()->withoutGlobalScopes()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            if ($lockedBranch->memberships()->exists()
                || Product::query()->withoutGlobalScopes()->where('branch_id', $lockedBranch->id)->exists()
                || Order::query()->withoutGlobalScopes()->where('branch_id', $lockedBranch->id)->exists()
                || Invitation::query()->where('branch_id', $lockedBranch->id)->exists()
                || AdminAuditLog::query()->withoutGlobalScopes()->where('branch_id', $lockedBranch->id)->exists()) {
                abort(409, 'This branch is in use and cannot be deleted.');
            }
            $lockedBranch->delete();
        });

        AdminAuditLog::record(
            'branch.delete',
            "Deleted branch '{$name}'.",
            ['name' => $name, 'company_id' => $branch->company_id]
        );

        return redirect()->route('admin.settings.features')->with('success', "Branch '{$name}' deleted.");
    }

    private function authorizeShopOwner(): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $access = app(BusinessAccessService::class);
        $company = $access->installationCompany();
        abort_unless($access->canManageCompany($user, (int) $company->id), 403);
    }
}
