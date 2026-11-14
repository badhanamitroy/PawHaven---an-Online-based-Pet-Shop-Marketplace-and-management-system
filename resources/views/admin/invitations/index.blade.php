@extends('layouts.admin')

@section('title', 'Staff & Seller Invitations')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Staff &amp; Seller Invitations</h1>
            <p class="text-xs text-[#69736B] mt-1">Careers is the primary employee hiring path. Use these direct invitations only for marketplace seller invitations or exceptional branch-manager recovery.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.invitations.create') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-[#245C45] px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-[#173F32] transition-all"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Issue New Invitation</span>
            </a>
        </div>
    </div>

    {{-- Session Flash Alerts --}}
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error') || $errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('error') ?? 'Please check the errors below:' }}</span>
            </div>
            @if($errors->any())
                <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Total Issued</span>
            <div class="mt-2 text-2xl font-bold text-[#173F32]">{{ $totalCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">All records</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Pending Valid</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">{{ $pendingCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Awaiting candidate</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Accepted</span>
            <div class="mt-2 text-2xl font-bold text-emerald-700">{{ $acceptedCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Successfully onboarded</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Seller Invites</span>
            <div class="mt-2 text-2xl font-bold text-purple-700">{{ $sellerCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Marketplace vendors</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm col-span-2 sm:col-span-1">
            <span class="text-xs font-semibold text-[#69736B]">Branch Managers</span>
            <div class="mt-2 text-2xl font-bold text-blue-700">{{ $managerCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Branch store leaders</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.invitations.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search candidate by name or email..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
            <div>
                <select
                    name="role"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                >
                    <option value="">All Roles</option>
                    <option value="seller" {{ request('role') === 'seller' ? 'selected' : '' }}>Seller</option>
                    <option value="manager" {{ request('role') === 'manager' ? 'selected' : '' }}>Branch Manager</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <select
                    name="status"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                >
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                    <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>Revoked</option>
                </select>
                <button
                    type="submit"
                    class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                >
                    Filter
                </button>
                @if(request()->hasAny(['search', 'role', 'status']))
                    <a href="{{ route('admin.invitations.index') }}" class="text-xs text-[#69736B] hover:underline">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Invitations Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($invitations->isEmpty())
            <div class="p-12 text-center text-xs text-[#69736B] space-y-2">
                <div class="text-2xl font-bold text-[#173F32]">No invitations found</div>
                <p>Click "Issue New Invitation" above to onboard a Marketplace Seller or Branch Manager.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Candidate</th>
                            <th class="px-3 py-3.5">Intended Role</th>
                            <th class="px-3 py-3.5">Branch Scope</th>
                            <th class="px-3 py-3.5">Invited By</th>
                            <th class="px-3 py-3.5">Status</th>
                            <th class="px-3 py-3.5">Expiry / Timeline</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($invitations as $inv)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3.5 pl-6 pr-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-[#245C45]/10 font-bold text-xs text-[#245C45]">
                                            {{ strtoupper(substr($inv->name ?: $inv->email, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-[#173F32]">
                                                {{ $inv->name ?: 'Named upon registration' }}
                                            </div>
                                            <div class="text-[#69736B] font-mono text-[11px]">{{ $inv->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    @if($inv->isSeller())
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset bg-purple-50 text-purple-800 ring-purple-600/20">
                                            Seller
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset bg-blue-50 text-blue-800 ring-blue-600/20">
                                            Branch Manager
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    @if($inv->isBranchManager() && $inv->branch)
                                        <div class="font-bold text-[#173F32]">{{ $inv->branch->name }}</div>
                                        <div class="text-[10px] text-[#69736B] font-mono">{{ $inv->branch->code }}</div>
                                    @else
                                        <span class="text-gray-400 font-normal">All Marketplace</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-[#69736B]">
                                    {{ $inv->invitedBy?->name ?? 'System Admin' }}
                                </td>
                                <td class="px-3 py-3.5">
                                    @php
                                        $status = $inv->status();
                                        $badge = match($status) {
                                            'accepted' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
                                            'pending' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
                                            'revoked' => 'bg-rose-50 text-rose-800 ring-rose-600/20',
                                            'expired' => 'bg-gray-100 text-gray-700 ring-gray-500/20',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset {{ $badge }}">
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-[#69736B] text-[11px]">
                                    @if($inv->isAccepted())
                                        <div>Accepted {{ $inv->accepted_at->format('M d, Y') }}</div>
                                        <div class="text-[10px] text-emerald-700">User ID #{{ $inv->accepted_user_id }}</div>
                                    @elseif($inv->isRevoked())
                                        <div>Revoked {{ $inv->revoked_at->format('M d, Y') }}</div>
                                    @elseif($inv->isExpired())
                                        <div class="text-rose-600 font-semibold">Expired {{ $inv->expires_at->diffForHumans() }}</div>
                                    @else
                                        <div class="font-medium text-[#173F32]">Expires in {{ $inv->expires_at->diffForHumans(['parts' => 2]) }}</div>
                                        <div class="text-[10px] text-gray-400">{{ $inv->expires_at->format('M d, g:i A') }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 pl-3 pr-6 text-right space-x-1">
                                    @if($inv->isPending())
                                        <form method="POST" action="{{ route('admin.invitations.resend', $inv) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="rounded-lg bg-[#FAF8F2] border border-[#245C45]/20 px-2.5 py-1 text-[11px] font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                                                title="Rotate token and dispatch fresh email"
                                            >
                                                Resend
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('admin.invitations.revoke', $inv) }}"
                                            class="inline"
                                            onsubmit="return confirm('Are you sure you want to revoke this invitation? The candidate will no longer be able to use the link.');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="rounded-lg bg-rose-50 border border-rose-200 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-600 hover:text-white transition-colors"
                                            >
                                                Revoke
                                            </button>
                                        </form>
                                    @elseif($inv->isExpired())
                                        <form method="POST" action="{{ route('admin.invitations.resend', $inv) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="rounded-lg bg-amber-50 border border-amber-200 px-2.5 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-600 hover:text-white transition-colors"
                                                title="Re-open and send fresh 72-hour invitation"
                                            >
                                                Re-Invite
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[11px] text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($invitations->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $invitations->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
