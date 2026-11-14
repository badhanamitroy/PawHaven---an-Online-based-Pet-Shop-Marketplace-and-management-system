<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $oldRole = $user->role ?? 'customer';

        if ($user->isStaff() || $newRole === 'staff') {
            return redirect()->route('admin.users.index')
                ->with('error', 'Employee account roles and access are managed by the Shop Owner through business assignments.');
        }

        if ($user->isSeller() && $newRole !== 'seller'
            && ($user->petListings()->exists() || $user->sellerOrders()->exists())) {
            return redirect()->route('admin.users.index')
                ->with('error', 'This Marketplace Seller account has owned marketplace records and cannot change identity role.');
        }

        // Safeguard 1: Prevent an admin from demoting themselves
        if ($user->id === auth()->id() && $newRole !== 'admin') {
            return redirect()->route('admin.users.index')
                ->with('error', 'For safety, you cannot remove your own administrator privileges.');
        }

        // Safeguard 2: Prevent demoting the last remaining admin in the entire system
        if ($oldRole === 'admin' && $newRole !== 'admin') {
            $remainingAdmins = User::where('role', 'admin')->where('id', '!=', $user->id)->count();
            if ($remainingAdmins === 0) {
                return redirect()->route('admin.users.index')
                    ->with('error', 'Cannot demote the last remaining administrator in the system.');
            }
        }

        $user->forceFill(['role' => $newRole])->save();

        AdminAuditLog::record(
            'user.role_change',
            "Changed role for user '{$user->name}' ({$user->email}) from '{$oldRole}' to '{$newRole}'.",
            [
                'target_user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $newRole,
            ]
        );

        return redirect()->route('admin.users.index')
            ->with('success', "Updated role for '{$user->name}' to '{$newRole}'.");
    }
}
