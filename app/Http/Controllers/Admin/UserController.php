<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->where(function ($query): void {
            $query->whereNull('role')->orWhere('role', '!=', 'staff');
        });

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        $adminCount = User::where('role', 'admin')->count();
        $staffCount = User::where('role', 'staff')->count();
        $sellerCount = User::where('role', 'seller')->count();
        $customerCount = User::customers()->count();

        return view('admin.users.index', compact(
            'users',
            'adminCount',
            'staffCount',
            'sellerCount',
            'customerCount'
        ));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'staff', 'seller', 'customer'])],
        ]);

        $newRole = $validated['role'];
        $error = DB::transaction(function () use ($user, $newRole): ?string {
            // Lock administrator rows in a consistent order so concurrent role changes
            // cannot both pass the last-administrator check.
            $administrators = User::query()->where('role', 'admin')
                ->orderBy('id')->lockForUpdate()->get();
            $target = $administrators->firstWhere('id', $user->getKey())
                ?? User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $oldRole = $target->role ?? 'customer';

            if ($target->isStaff() || $newRole === 'staff') {
                return 'Employee account roles and access are managed by the Shop Owner through business assignments.';
            }

            if ($target->isSeller() && $newRole !== 'seller'
                && ($target->petListings()->exists() || $target->sellerOrders()->exists())) {
                return 'This Marketplace Seller account has owned marketplace records and cannot change identity role.';
            }

            if ($target->getKey() === auth()->id() && $newRole !== 'admin') {
                return 'For safety, you cannot remove your own administrator privileges.';
            }

            if ($oldRole === 'admin' && $newRole !== 'admin' && $administrators->count() <= 1) {
                return 'Cannot demote the last remaining administrator in the system.';
            }

            $target->forceFill(['role' => $newRole])->save();
            AdminAuditLog::record(
                'user.role_change',
                "Changed role for user #{$target->getKey()} from '{$oldRole}' to '{$newRole}'.",
                [
                    'target_user_id' => $target->getKey(),
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                ]
            );

            return null;
        }, 3);

        if ($error !== null) {
            return redirect()->route('admin.users.index')->with('error', $error);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Updated role for '{$user->name}' to '{$newRole}'.");
    }
}
