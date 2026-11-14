<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationRequest;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeInvitationList($request->user());
        $query = Invitation::query()
            ->with(['branch', 'company', 'invitedBy', 'acceptedUser'])
            ->latest();

        if ($request->user()->isAdmin()) {
            $query->where('role', 'seller');
        } else {
            $company = app(BusinessAccessService::class)->installationCompany();
            $query->where('company_id', $company->id)->whereIn('role', ['manager', 'branch_manager']);
        }

        if ($request->filled('role')) {
            $role = $request->input('role');
            if ($role === 'manager' || $role === 'branch_manager') {
                $query->whereIn('role', ['manager', 'branch_manager']);
            } elseif ($role === 'seller') {
                $query->where('role', 'seller');
            }
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            match ($status) {
                'pending' => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now()),
                'accepted' => $query->whereNotNull('accepted_at'),
                'revoked' => $query->whereNotNull('revoked_at'),
                'expired' => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '<=', now()),
                default => null,
            };
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $invitations = $query->paginate(15)->withQueryString();

        $totalCount = Invitation::count();
        $pendingCount = Invitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();
        $acceptedCount = Invitation::query()->whereNotNull('accepted_at')->count();
        $sellerCount = Invitation::query()->where('role', 'seller')->count();
        $managerCount = Invitation::query()->whereIn('role', ['manager', 'branch_manager'])->count();

        return view('admin.invitations.index', compact(
            'invitations',
            'totalCount',
            'pendingCount',
            'acceptedCount',
            'sellerCount',
            'managerCount'
        ));
    }

    public function create(): View
    {
        $user = request()->user();
        $this->authorizeInvitationList($user);
        $canInviteEmployees = $user instanceof User && app(BusinessAccessService::class)
            ->activeMemberships($user)->contains(fn ($membership): bool => $membership->role === BusinessAccessService::OWNER);
        $branches = $canInviteEmployees
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('admin.invitations.create', [
            'branches' => $branches,
            'canInviteEmployees' => $canInviteEmployees,
            'canInviteMarketplaceSellers' => $user instanceof User && $user->isAdmin(),
        ]);
    }

    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        try {
            $result = $this->invitationService->createInvitation(
                $request->validated(),
                $request->user()
            );

            return redirect()->route('admin.invitations.index')
                ->with('success', "Official invitation sent successfully to {$result['invitation']->email}.");
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function resend(Invitation $invitation, Request $request): RedirectResponse
    {
        try {
            $this->invitationService->resendInvitation($invitation, $request->user());

            return back()->with('success', "A fresh invitation email has been dispatched to {$invitation->email}.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function revoke(Invitation $invitation, Request $request): RedirectResponse
    {
        try {
            $this->invitationService->revokeInvitation($invitation, $request->user());

            return back()->with('success', "The invitation for {$invitation->email} was successfully revoked.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function authorizeInvitationList(?User $user): void
    {
        abort_unless($user instanceof User, 403);
        if ($user->isAdmin()) {
            return;
        }

        abort_unless(app(BusinessAccessService::class)->activeMemberships($user)->contains(
            fn ($membership): bool => $membership->role === BusinessAccessService::OWNER
                && $membership->branch_id === null
        ), 403);
    }
}
