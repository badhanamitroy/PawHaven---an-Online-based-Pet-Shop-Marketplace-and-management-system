@extends('layouts.admin')

@section('title', 'Feature Flags & Modular Architecture')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Feature Flags &amp; Modular Architecture</h1>
        <p class="text-xs text-[#69736B] mt-1">Configure optional platform capabilities. In accordance with PawHaven's business model, multi-staff and multi-branch are independent options that remain optional.</p>
    </div>

    {{-- Feature Flags Form --}}
    <form method="POST" action="{{ route('admin.settings.features.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-[#173F32] border-b border-gray-100 pb-3">Operational Flags</h2>

            @php
                $multiStaff = ($settings['features.multi_staff_enabled'] ?? 'false') === 'true';
                $multiBranch = ($settings['features.multi_branch_enabled'] ?? 'false') === 'true';
            @endphp

            <div class="space-y-4">
                {{-- Multi-Staff Flag --}}
                <div class="flex items-start gap-4 p-4 rounded-xl border {{ $multiStaff ? 'border-emerald-200 bg-emerald-50/30' : 'border-gray-200 bg-gray-50/50' }}">
                    <div class="pt-0.5">
                        <input
                            type="checkbox"
                            name="multi_staff_enabled"
                            id="multi_staff_enabled"
                            value="1"
                            {{ old('multi_staff_enabled', $multiStaff ? '1' : '0') == '1' ? 'checked' : '' }}
                            class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                        />
                    </div>
                    <label for="multi_staff_enabled" class="flex-1 cursor-pointer">
                        <span class="text-xs font-bold text-[#173F32] block">Enable Multi-Staff Account Management</span>
                        <p class="text-[11px] text-[#69736B] mt-0.5">
                            Allows designating multiple shop employees with staff permissions to fulfill orders and update product inventory without granting full administrator rights.
                        </p>
                    </label>
                </div>

                {{-- Multi-Branch Flag --}}
                <div class="flex items-start gap-4 p-4 rounded-xl border {{ $multiBranch ? 'border-emerald-200 bg-emerald-50/30' : 'border-gray-200 bg-gray-50/50' }}">
                    <div class="pt-0.5">
                        <input
                            type="checkbox"
                            name="multi_branch_enabled"
                            id="multi_branch_enabled"
                            value="1"
                            {{ old('multi_branch_enabled', $multiBranch ? '1' : '0') == '1' ? 'checked' : '' }}
                            class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                        />
                    </div>
                    <label for="multi_branch_enabled" class="flex-1 cursor-pointer">
                        <span class="text-xs font-bold text-[#173F32] block">Enable Multi-Branch Physical Fulfillment</span>
                        <p class="text-[11px] text-[#69736B] mt-0.5">
                            Allows defining multiple physical store branches and dispatch warehouses. This is an independent feature and is not required for multi-staff operation.
                        </p>
                    </label>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-3 flex justify-end">
                <button
                    type="submit"
                    class="rounded-xl bg-[#245C45] px-6 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
                >
                    Save Feature Flags
                </button>
            </div>
        </div>
    </form>

    {{-- Physical Branches Management (visible when multi-branch flag is enabled or branches exist) --}}
    @if($multiBranch || $branches->isNotEmpty())
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-[#173F32]">Physical Store Branches &amp; Dispatch Centers</h2>
                    <p class="text-xs text-[#69736B]">Manage local branch stores and fulfillment centers.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Add Branch Form --}}
                <div class="lg:col-span-1">
                    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32] mb-3">Add Store Branch</h3>

                        <form method="POST" action="{{ route('admin.settings.branches.store') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="branch_name" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                                    Branch Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="name"
                                    id="branch_name"
                                    value="{{ old('name') }}"
                                    required
                                    placeholder="e.g. Downtown Flagship"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                                />
                            </div>

                            <div>
                                <label for="branch_code" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                                    Branch Code <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="code"
                                    id="branch_code"
                                    value="{{ old('code') }}"
                                    required
                                    placeholder="e.g. BR-DT01"
                                    class="w-full font-mono uppercase rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                                />
                            </div>

                            <div>
                                <label for="branch_city" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                                    City <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="city"
                                    id="branch_city"
                                    value="{{ old('city') }}"
                                    required
                                    placeholder="e.g. Seattle"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                                />
                            </div>

                            <div>
                                <label for="branch_address" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                                    Street Address <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="address"
                                    id="branch_address"
                                    value="{{ old('address') }}"
                                    required
                                    placeholder="e.g. 456 Pine Ave"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                                />
                            </div>

                            <div>
                                <label for="branch_phone" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                                    Phone Number
                                </label>
                                <input
                                    type="text"
                                    name="phone"
                                    id="branch_phone"
                                    value="{{ old('phone') }}"
                                    placeholder="e.g. +1 (555) 012-3456"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                                />
                            </div>

                            <button
                                type="submit"
                                class="w-full rounded-xl bg-[#245C45] py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
                            >
                                Add Branch
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Branches Table --}}
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
                        <div class="border-b border-gray-100 bg-[#FAF8F2] px-6 py-3.5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Configured Branches ({{ $branches->count() }})</h3>
                        </div>

                        @if($branches->isEmpty())
                            <div class="p-8 text-center text-xs text-[#69736B]">
                                No physical branches added yet. PawHaven operates as a unified single dispatch center.
                            </div>
                        @else
                            <div class="divide-y divide-gray-100">
                                @foreach($branches as $branch)
                                    <div class="p-4 flex items-center justify-between hover:bg-gray-50/70 transition-colors">
                                        <div>
                                            <div class="font-bold text-xs text-[#173F32] flex items-center gap-2">
                                                <span>{{ $branch->name }}</span>
                                                <span class="font-mono text-[10px] bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">{{ $branch->code }}</span>
                                            </div>
                                            <div class="text-[11px] text-[#69736B]">
                                                {{ $branch->address }}, {{ $branch->city }}
                                                @if($branch->phone)&bull; {{ $branch->phone }}@endif
                                            </div>
                                        </div>
                                        <div>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.settings.branches.destroy', $branch) }}"
                                                onsubmit="return confirm('Delete branch \'{{ $branch->name }}\'?');"
                                                class="inline"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="rounded p-1 text-gray-400 hover:text-red-600 transition-colors"
                                                    title="Delete Branch"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

