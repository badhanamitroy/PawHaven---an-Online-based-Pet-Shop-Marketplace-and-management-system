<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\BusinessMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BusinessAccessController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', BusinessMembership::class);
        $actor = auth()->user();
        $access = app(BusinessAccessService::class);
        $setupAdmin = $actor instanceof User && $access->isInitialSetupAdmin($actor);
        $memberships = $actor instanceof User ? $access->activeMemberships($actor) : collect();
        $companyIds = $setupAdmin
            ? Company::query()->withoutGlobalScopes()->pluck('id')->all()
            : $memberships->filter(fn (BusinessMembership $membership): bool =>
                in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
            )->pluck('company_id')->unique()->values()->all();
        $branchIds = $memberships->where('role', BusinessAccessService::MANAGER)
            ->pluck('branch_id')->filter()->unique()->values()->all();

        $companies = Company::query()->when($setupAdmin, fn ($query) => $query->withoutGlobalScopes())
            ->whereIn('id', $companyIds)
            ->with([
                'branches' => function ($query) use ($setupAdmin, $branchIds): void {
                    $query->orderBy('name');
                    if (! $setupAdmin && $branchIds !== []) {
                        $query->whereIn('id', $branchIds);
                    }
                },
                'memberships' => function ($query) use ($setupAdmin, $branchIds): void {
                    $query->where('is_active', true)->with(['user', 'branch'])->orderBy('id');
                    if (! $setupAdmin && $branchIds !== []) {
                        $query->whereIn('branch_id', $branchIds);
                    }
                },
            ])
            ->orderBy('name')
            ->get();
        $staffQuery = User::query()->where(function ($query) use ($setupAdmin): void {
            $query->where('role', 'staff');
            if ($setupAdmin) {
                $query->orWhere(function ($customerQuery): void {
                    $customerQuery->where(function ($roles): void {
                        $roles->whereIn('role', ['customer', 'user'])->orWhereNull('role')->orWhere('role', '');
                    })->whereNotNull('email_verified_at');
                });
            }
        });
        if (! $setupAdmin) {
            $staffQuery->where(function ($query) use ($companyIds): void {
                $query->whereNotExists(function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('business_memberships')
                        ->whereColumn('business_memberships.user_id', 'users.id');
                })->orWhereExists(function ($subquery) use ($companyIds): void {
                    $subquery->selectRaw('1')
                        ->from('business_memberships')
                        ->whereColumn('business_memberships.user_id', 'users.id')
                        ->whereIn('business_memberships.company_id', $companyIds);
                });
            });

            if ($branchIds !== [] && ! $memberships->contains(fn (BusinessMembership $membership): bool =>
                $membership->role === BusinessAccessService::OWNER
            )) {
                $staffQuery->whereExists(function ($subquery) use ($branchIds): void {
                    $subquery->selectRaw('1')->from('business_memberships')
                        ->whereColumn('business_memberships.user_id', 'users.id')
                        ->where('business_memberships.is_active', true)
                        ->whereIn('business_memberships.branch_id', $branchIds);
                });
            }
        }
        $staff = $staffQuery->orderBy('name')->get();

        return view('admin.business.index', [
            'companies' => $companies,
            'staff' => $staff,
            'canCreateCompany' => Gate::allows('create', Company::class),
            'canManageBranches' => $actor instanceof User && $companyIds !== []
                && $access->canManageCompany($actor, (int) $companyIds[0]),
            'canAssignOwner' => $setupAdmin && $companies->isNotEmpty()
                && ! BusinessMembership::query()->whereIn('company_id', $companyIds)
                    ->where('role', BusinessAccessService::OWNER)->where('is_active', true)
                    ->whereNull('branch_id')->exists(),
        ]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $company = DB::transaction(function () use ($validated): Company {
            User::query()->where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            if (Company::query()->withoutGlobalScopes()->exists()) {
                throw ValidationException::withMessages([
                    'company' => 'This installation already has a company configured.',
                ]);
            }

            $baseSlug = Str::slug($validated['name']) ?: Str::uuid()->toString();
            $slug = $baseSlug;
            $suffix = 1;
            while (Company::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $company = Company::create([...$validated, 'slug' => $slug, 'is_active' => true]);
            AdminAuditLog::record('company.create', "Created company #{$company->id}.", ['company_id' => $company->id]);

            return $company;
        });

        return redirect()->route('admin.business.index')
            ->with('success', "Company '{$company->name}' was created.");
    }

    public function updateCompany(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $validated['is_active'] && ! $request->user()->isAdmin()) {
            return back()->with('error', 'Only an administrator can deactivate a company.');
        }

        $company->update($validated);
        AdminAuditLog::record(
            'company.update',
            "Updated company #{$company->id}.",
            ['company_id' => $company->id]
        );

        return redirect()->route('admin.business.index')
            ->with('success', "Company '{$company->name}' was updated.");
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $company = Company::query()->whereKey($validated['company_id'])->firstOrFail();
        if (! $company->is_active) {
            return back()->withErrors(['company_id' => 'The selected company is inactive.'])->withInput();
        }
        abort_unless(app(BusinessAccessService::class)->canManageCompany($request->user(), $company->id), 403);
        Gate::authorize('manageMemberships', [BusinessMembership::class, $company]);

        $branch = DB::transaction(function () use ($company, $validated): Branch {
            Company::query()->withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            return Branch::create([
                ...$validated,
                'code' => strtoupper($validated['code']),
                'is_active' => true,
            ]);
        });
        AdminAuditLog::record(
            'branch.create',
            "Created branch #{$branch->id} for company #{$company->id}.",
            ['company_id' => $company->id, 'branch_code' => $branch->code]
        );

        return redirect()->route('admin.business.index')
            ->with('success', "Branch '{$branch->name}' was created.");
    }

    public function storeMembership(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(['owner', 'manager', 'seller'])],
        ]);

        $company = Company::query()->withoutGlobalScopes()->whereKey($validated['company_id'])->firstOrFail();
        if (! $company->is_active) {
            return back()->withErrors(['company_id' => 'The selected company is inactive.'])->withInput();
        }
        app(BusinessAccessService::class)->installationCompany((int) $company->id);
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        Gate::authorize('manageMemberships', [BusinessMembership::class, $company, $branchId]);

        $staffUser = User::query()->whereKey($validated['user_id'])->firstOrFail();
        $initialOwnerAssignment = $validated['role'] === BusinessAccessService::OWNER
            && $branchId === null
            && app(BusinessAccessService::class)->isInitialSetupAdmin($request->user());
        if (! $staffUser->isStaff() && ! $initialOwnerAssignment) {
            return back()->withErrors([
                'user_id' => 'Only accounts with the staff role can receive a business membership.',
            ])->withInput();
        }

        app(BusinessMembershipService::class)->assign(
            $request->user(),
            $staffUser,
            $company,
            $branchId,
            $validated['role'],
        );
        AdminAuditLog::record(
            'business.membership.assign',
            "Assigned user #{$staffUser->id} to company #{$company->id}.",
            [
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'user_id' => $staffUser->id,
                'membership_role' => $validated['role'],
            ]
        );

        return redirect()->route('admin.business.index')
            ->with('success', "Business access for '{$staffUser->name}' was saved.");
    }

    public function destroyMembership(BusinessMembership $membership): RedirectResponse
    {
        Gate::authorize('delete', $membership);
        app(BusinessMembershipService::class)->deactivate(request()->user(), $membership);

        $companyId = $membership->company_id;
        $branchId = $membership->branch_id;
        AdminAuditLog::record(
            'business.membership.remove',
            "Removed membership #{$membership->id} from company #{$companyId}.",
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'membership_id' => $membership->id,
            ]
        );

        return redirect()->route('admin.business.index')
            ->with('success', 'Business membership was removed.');
    }
}
