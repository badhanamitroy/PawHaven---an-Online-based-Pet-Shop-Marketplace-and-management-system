<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmploymentOffer;
use App\Models\JobApplication;
use App\Services\BusinessAccessService;
use App\Services\RecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeeApprovalController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $company = app(BusinessAccessService::class)->installationCompany();
        abort_unless($user && ! $user->isAdmin() && app(BusinessAccessService::class)->canManageEmployees($user, (int) $company->id), 403);

        $applications = JobApplication::query()->where('status', 'awaiting_owner_approval')
            ->with(['posting', 'offers' => fn ($query) => $query->where('status', 'accepted')->with([
                'branch' => fn ($branch) => $branch->withoutGlobalScopes(),
                'employee',
            ])])
            ->latest('reviewed_at')->get();

        return view('admin.careers.approvals.index', compact('applications'));
    }

    public function approve(EmploymentOffer $offer, RecruitmentService $recruitment): RedirectResponse
    {
        $this->authorizeOwner();
        $recruitment->approveEmployee(request()->user(), $offer);

        return back()->with('success', 'Employee access approved. A password setup link was sent to the employee.');
    }

    public function reject(EmploymentOffer $offer, RecruitmentService $recruitment): RedirectResponse
    {
        $this->authorizeOwner();
        $recruitment->rejectEmployee(request()->user(), $offer);

        return back()->with('success', 'Employee access was declined and remains inactive.');
    }

    private function authorizeOwner(): void
    {
        $user = request()->user();
        $company = app(BusinessAccessService::class)->installationCompany();
        abort_unless($user && ! $user->isAdmin() && app(BusinessAccessService::class)->canManageEmployees($user, (int) $company->id), 403);
    }
}
