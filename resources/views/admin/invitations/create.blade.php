@extends('layouts.admin')

@section('title', 'Issue Onboarding Invitation')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="{
    role: '{{ old('role', $canInviteEmployees ? 'manager' : 'seller') }}',
    name: '{{ old('name') }}',
    email: '{{ old('email') }}',
    branchId: '{{ old('branch_id') }}',
    branches: {{ Js::from($branches) }},
    get selectedBranchName() {
        const found = this.branches.find(b => String(b.id) === String(this.branchId));
        return found ? found.name + ' (' + found.code + ')' : 'None Selected';
    }
}">
    {{-- Breadcrumb Navigation --}}
    <div class="flex items-center gap-2 text-xs text-[#69736B]">
        <a href="{{ route('admin.invitations.index') }}" class="hover:text-[#173F32] hover:underline">&larr; Staff &amp; Seller Invitations</a>
        <span>/</span>
        <span class="text-[#173F32] font-semibold">Issue Official Invitation</span>
    </div>

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Issue Exceptional Invitation</h1>
        <p class="mt-2 text-sm text-[#69736B]">Use Careers for ordinary employee recruitment. This invitation form is retained for marketplace sellers and exceptional branch-manager recovery.</p>
        <p class="text-xs text-[#69736B] mt-1">
            @if($canInviteEmployees)
                Invite a Branch Manager to the company branch you select. Employee Seller assignments are managed separately by the Shop Owner.
            @else
                Invite an independent Marketplace Seller. This does not create an employee account.
            @endif
        </p>
    </div>

    {{-- Error Banner --}}
    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Please correct the issues below:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Container --}}
    <form method="POST" action="{{ route('admin.invitations.store') }}" class="space-y-6">
        @csrf

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-6">
            {{-- Step 1: Intended Role Selection --}}
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-2">
                    1. Select Onboarding Role
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @if($canInviteMarketplaceSellers)
                    <label
                        class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition-all"
                        :class="role === 'seller' ? 'border-[#245C45] bg-[#FAF8F2] ring-2 ring-[#245C45]/20' : 'border-gray-200 hover:border-gray-300'"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">🏪</span>
                                <span class="text-sm font-bold text-[#173F32]">Marketplace Seller</span>
                            </div>
                            <input type="radio" name="role" value="seller" x-model="role" class="text-[#245C45] focus:ring-[#245C45]" />
                        </div>
                        <p class="text-[11px] text-[#69736B] mt-2 leading-relaxed">
                            Authorized to list pets, manage seller requests, and sell approved marketplace goods platform-wide.
                        </p>
                    </label>
                    @endif

                    @if($canInviteEmployees)
                    <label
                        class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition-all"
                        :class="role === 'manager' ? 'border-[#245C45] bg-[#FAF8F2] ring-2 ring-[#245C45]/20' : 'border-gray-200 hover:border-gray-300'"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">🏢</span>
                                <span class="text-sm font-bold text-[#173F32]">Branch Manager</span>
                            </div>
                            <input type="radio" name="role" value="manager" x-model="role" class="text-[#245C45] focus:ring-[#245C45]" />
                        </div>
                        <p class="text-[11px] text-[#69736B] mt-2 leading-relaxed">
                            Staff authority is limited to the assigned branch. The manager can monitor branch operations but cannot change catalogue or inventory quantities.
                        </p>
                    </label>
                    @endif
                </div>
            </div>

            {{-- Step 2: Branch Assignment (for Branch Manager only) --}}
            <div x-show="role === 'manager'" x-cloak class="p-4 rounded-xl bg-blue-50/50 border border-blue-200/60 space-y-2">
                <label for="branch_id" class="block text-xs font-bold uppercase tracking-wider text-[#173F32]">
                    2. Assigned Operational Branch <span class="text-rose-600">*</span>
                </label>
                <select
                    id="branch_id"
                    name="branch_id"
                    x-model="branchId"
                    class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    :required="role === 'manager'"
                >
                    <option value="">-- Choose an Active Branch --</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }}) &mdash; {{ $b->city ?? 'Central' }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-[#69736B]">
                    The recipient cannot change their assigned branch during registration. All operational access is bound to this branch.
                </p>
            </div>

            {{-- Step 3: Candidate Information --}}
            <div class="space-y-4 pt-2 border-t border-gray-100">
                <div class="block text-xs font-bold uppercase tracking-wider text-[#173F32]">
                    3. Candidate Details
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="candidate_name" class="block text-xs font-semibold text-[#173F32] mb-1">
                            Candidate Full Name <span class="text-gray-400 font-normal">(Optional)</span>
                        </label>
                        <input
                            type="text"
                            id="candidate_name"
                            name="name"
                            x-model="name"
                            placeholder="e.g. Eleanor Vance"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>

                    <div>
                        <label for="candidate_email" class="block text-xs font-semibold text-[#173F32] mb-1">
                            Candidate Email Address <span class="text-rose-600">*</span>
                        </label>
                        <input
                            type="email"
                            id="candidate_email"
                            name="email"
                            x-model="email"
                            required
                            placeholder="candidate@example.com"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>
                </div>
            </div>

            {{-- Step 4: Pre-flight Summary Card --}}
            <div class="p-4 rounded-xl bg-[#FAF8F2] border border-[#202820]/10 text-xs space-y-2">
                <div class="font-bold text-[#173F32] flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-[#245C45]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Invitation Pre-flight Summary</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-[11px] text-[#69736B] pt-1">
                    <div>Recipient: <span class="font-bold text-[#173F32]" x-text="email ? email : '(Enter email above)'"></span></div>
                    <div>Position: <span class="font-bold text-[#245C45]" x-text="role === 'seller' ? 'Marketplace Seller' : 'Branch Manager'"></span></div>
                    <div x-show="role === 'manager'">Branch: <span class="font-bold text-[#173F32]" x-text="selectedBranchName"></span></div>
                    <div>Validity Window: <span class="font-bold text-[#173F32]">72 Hours from dispatch</span></div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a
                    href="{{ route('admin.invitations.index') }}"
                    class="rounded-xl px-4 py-2.5 text-xs font-semibold text-[#69736B] hover:text-[#173F32] hover:bg-gray-100 transition-colors"
                >
                    Cancel
                </a>
                <button
                    type="submit"
                    class="rounded-xl bg-[#245C45] px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-[#173F32] transition-all flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>Send Official Invitation Email</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
