@extends('layouts.admin')

@section('title', 'Companies & Branch Access')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <header>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Companies &amp; Branch Access</h1>
        <p class="mt-1 text-sm text-[#69736B]">
            Manage operational companies, locations, and least-privilege staff assignments. Payments are outside this workspace.
        </p>
    </header>

    @if (session('success') || session('error'))
        <div class="rounded-xl border border-[#245C45]/15 bg-white p-4 text-sm">
            {{ session('success') ?? session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($canCreateCompany)
        <section class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#173F32]">Add a company</h2>
            <form method="POST" action="{{ route('admin.business.companies.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <input name="name" required maxlength="150" value="{{ old('name') }}" placeholder="Company name" class="rounded-lg border-gray-300 text-sm">
                <input name="legal_name" maxlength="180" value="{{ old('legal_name') }}" placeholder="Legal name (optional)" class="rounded-lg border-gray-300 text-sm">
                <input name="contact_email" type="email" value="{{ old('contact_email') }}" placeholder="Contact email" class="rounded-lg border-gray-300 text-sm">
                <input name="phone" maxlength="50" value="{{ old('phone') }}" placeholder="Phone" class="rounded-lg border-gray-300 text-sm">
                <textarea name="address" maxlength="1000" placeholder="Business address" class="rounded-lg border-gray-300 text-sm md:col-span-2">{{ old('address') }}</textarea>
                <button class="w-fit rounded-lg bg-[#245C45] px-4 py-2 text-sm font-semibold text-white">Create company</button>
            </form>
        </section>
    @endif

    <div class="space-y-5">
        @forelse ($companies as $company)
            <section class="space-y-5 rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-[#173F32]">{{ $company->name }}</h2>
                        <p class="text-xs text-[#69736B]">{{ $company->legal_name ?: $company->slug }} · {{ $company->is_active ? 'Active' : 'Inactive' }}</p>
                    </div>
                    @can('update', $company)
                        <form method="POST" action="{{ route('admin.business.companies.update', $company) }}" class="grid w-full max-w-3xl gap-2 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <input name="name" required maxlength="150" value="{{ $company->name }}" aria-label="Company name" class="rounded-lg border-gray-300 text-xs">
                            <input name="legal_name" maxlength="180" value="{{ $company->legal_name }}" aria-label="Legal name" placeholder="Legal name" class="rounded-lg border-gray-300 text-xs">
                            <input name="contact_email" type="email" value="{{ $company->contact_email }}" aria-label="Company email" placeholder="Contact email" class="rounded-lg border-gray-300 text-xs">
                            <input name="phone" maxlength="50" value="{{ $company->phone }}" aria-label="Company phone" placeholder="Phone" class="rounded-lg border-gray-300 text-xs">
                            <input name="address" maxlength="1000" value="{{ $company->address }}" aria-label="Company address" placeholder="Address" class="rounded-lg border-gray-300 text-xs">
                            <input type="hidden" name="is_active" value="{{ $company->is_active ? '0' : '1' }}">
                            <div class="flex gap-2">
                                <button class="rounded-lg border border-[#245C45]/30 px-3 py-2 text-xs font-semibold text-[#245C45]">Save details</button>
                                <button class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700" name="is_active" value="{{ $company->is_active ? '0' : '1' }}">
                                    {{ $company->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>
                        </form>
                    @endcan
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-[#173F32]">Branches</h3>
                        <ul class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                            @forelse ($company->branches as $branch)
                                <li class="flex justify-between gap-3 px-3 py-2 text-sm">
                                    <span>{{ $branch->name }} <span class="text-xs text-gray-500">({{ $branch->code }})</span></span>
                                    <span class="text-xs text-gray-500">{{ $branch->city ?: 'No city' }} · {{ $branch->is_active ? 'Active' : 'Inactive' }}</span>
                                </li>
                            @empty
                                <li class="px-3 py-2 text-sm text-gray-500">No branches registered.</li>
                            @endforelse
                        </ul>

                        @if($canManageBranches)
                            <form method="POST" action="{{ route('admin.business.branches.store') }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                <input name="name" required maxlength="100" placeholder="Branch name" class="rounded-lg border-gray-300 text-sm">
                                <input name="code" required maxlength="50" placeholder="Branch code" class="rounded-lg border-gray-300 text-sm">
                                <input name="address" required maxlength="255" placeholder="Address" class="rounded-lg border-gray-300 text-sm">
                                <input name="city" maxlength="100" placeholder="City" class="rounded-lg border-gray-300 text-sm">
                                <input name="phone" maxlength="50" placeholder="Phone" class="rounded-lg border-gray-300 text-sm">
                                <input name="email" type="email" placeholder="Branch email" class="rounded-lg border-gray-300 text-sm">
                                <button class="w-fit rounded-lg bg-[#245C45] px-4 py-2 text-xs font-semibold text-white sm:col-span-2">Add branch</button>
                            </form>
                        @endif
                    </div>

                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-[#173F32]">Staff assignments</h3>
                        <ul class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                            @forelse ($company->memberships as $membership)
                                <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <span>
                                        {{ $membership->user->name ?? 'Unknown account' }}
                                        <span class="text-xs text-gray-500">
                                            · {{ $membership->role }} · {{ $membership->branch->name ?? 'All branches' }}
                                        </span>
                                    </span>
                                    @can('delete', $membership)
                                        <form method="POST" action="{{ route('admin.business.memberships.destroy', $membership) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs font-semibold text-red-700">Remove</button>
                                        </form>
                                    @endcan
                                </li>
                            @empty
                                <li class="px-3 py-2 text-sm text-gray-500">No active staff memberships.</li>
                            @endforelse
                        </ul>

                        @can('manageMemberships', [\App\Models\BusinessMembership::class, $company])
                            <form method="POST" action="{{ route('admin.business.memberships.store') }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                <select name="user_id" required class="rounded-lg border-gray-300 text-sm">
                                    <option value="">Select staff account</option>
                                    @foreach ($staff as $staffUser)
                                        <option value="{{ $staffUser->id }}">{{ $staffUser->name }} ({{ $staffUser->email }})</option>
                                    @endforeach
                                </select>
                                <select name="branch_id" class="rounded-lg border-gray-300 text-sm">
                                    <option value="">All company branches</option>
                                    @foreach ($company->branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <select name="role" required class="rounded-lg border-gray-300 text-sm">
                                    <option value="manager">Branch Manager</option>
                                    <option value="seller">Employee Seller</option>
                                    @if($canAssignOwner)
                                        <option value="owner">Shop Owner</option>
                                    @endif
                                </select>
                                <button class="w-fit rounded-lg bg-[#245C45] px-4 py-2 text-xs font-semibold text-white">Save assignment</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">
                No companies are configured. An administrator can create the first company.
            </div>
        @endforelse
    </div>
</div>
@endsection
