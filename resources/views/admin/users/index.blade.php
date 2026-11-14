@extends('layouts.admin')

@section('title', 'Users & Staff Roles')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Users &amp; Staff Roles</h1>
            <p class="text-xs text-[#69736B] mt-1">Manage platform access, staff delegations, marketplace sellers, and customer accounts.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.invitations.create') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-[#245C45] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1d4b38] transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Invite Staff or Seller</span>
            </a>
            <a
                href="{{ route('admin.invitations.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-[#245C45]/20 bg-white px-4 py-2 text-xs font-semibold text-[#173F32] shadow-sm hover:bg-gray-50 transition-colors"
            >
                <span>View Invitations</span>
            </a>
        </div>
    </div>

    {{-- Role Breakdown KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Administrators</span>
            <div class="mt-2 text-2xl font-bold text-red-700">{{ $adminCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Full management access</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Shop Staff</span>
            <div class="mt-2 text-2xl font-bold text-blue-700">{{ $staffCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Catalog &amp; order fulfillment</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Marketplace Sellers</span>
            <div class="mt-2 text-2xl font-bold text-purple-700">{{ $sellerCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Community pet listers</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Customers</span>
            <div class="mt-2 text-2xl font-bold text-[#173F32]">{{ $customerCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Standard user accounts</div>
        </div>
    </div>

    {{-- Search & Filter --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search users by name or email..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
            <div class="flex items-center gap-2">
                <select
                    name="role"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                >
                    <option value="">All Roles</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="staff" {{ request('role') === 'staff' ? 'selected' : '' }}>Staff</option>
                    <option value="seller" {{ request('role') === 'seller' ? 'selected' : '' }}>Seller</option>
                    <option value="customer" {{ request('role') === 'customer' ? 'selected' : '' }}>Customer</option>
                </select>
                <button
                    type="submit"
                    class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                >
                    Filter
                </button>
                @if(request()->hasAny(['search', 'role']))
                    <a href="{{ route('admin.users.index') }}" class="text-xs text-[#69736B] hover:underline">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Users Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($users->isEmpty())
            <div class="p-8 text-center text-xs text-[#69736B]">
                No users match the search parameters.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">User</th>
                            <th class="px-3 py-3.5">Email</th>
                            <th class="px-3 py-3.5">Current Role</th>
                            <th class="px-3 py-3.5">Registered</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Role Assignment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($users as $u)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3.5 pl-6 pr-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-[#245C45]/10 font-bold text-xs text-[#245C45]">
                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                        </div>
                                        <div class="font-bold text-[#173F32]">
                                            {{ $u->name }}
                                            @if($u->id === auth()->id())
                                                <span class="ml-1 text-[10px] text-gray-400 font-normal">(You)</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-[#69736B] font-mono text-[11px]">{{ $u->email }}</td>
                                <td class="px-3 py-3.5">
                                    @php
                                        $roleBadge = match($u->role ?? 'customer') {
                                            'admin' => 'bg-red-50 text-red-800 ring-red-600/20',
                                            'staff' => 'bg-blue-50 text-blue-800 ring-blue-600/20',
                                            'seller' => 'bg-purple-50 text-purple-800 ring-purple-600/20',
                                            default => 'bg-gray-100 text-gray-700 ring-gray-500/20',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset {{ $roleBadge }}">
                                        {{ $u->role ?? 'customer' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-[#69736B]">{{ $u->created_at?->format('M d, Y') }}</td>
                                <td class="py-3.5 pl-3 pr-6 text-right">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.update-role', $u) }}"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <select
                                            name="role"
                                            class="rounded-lg border border-gray-200 px-2 py-1 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                                            {{ $u->id === auth()->id() ? 'disabled' : '' }}
                                        >
                                            <option value="customer" {{ ($u->role ?? 'customer') === 'customer' ? 'selected' : '' }}>Customer</option>
                                            <option value="seller" {{ $u->role === 'seller' ? 'selected' : '' }}>Seller</option>
                                            <option value="staff" {{ $u->role === 'staff' ? 'selected' : '' }}>Staff</option>
                                            <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                        </select>
                                        @if($u->id !== auth()->id())
                                            <button
                                                type="submit"
                                                class="rounded-lg bg-[#FAF8F2] border border-[#245C45]/20 px-2.5 py-1 text-[11px] font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                                            >
                                                Apply
                                            </button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

