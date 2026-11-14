<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\BusinessAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($user->isAdmin()) {
            $query = AdminAuditLog::query()->withoutGlobalScope('business_access')
                ->whereNull('company_id')->whereNull('branch_id')->with('user');
        } else {
            $canViewBusinessAudit = app(BusinessAccessService::class)->activeMemberships($user)->contains(
                fn ($membership): bool => in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
            );
            abort_unless($canViewBusinessAudit, 403);
            $query = AdminAuditLog::with('user');
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->input('action').'%');
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest()->paginate(25)->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }
}
