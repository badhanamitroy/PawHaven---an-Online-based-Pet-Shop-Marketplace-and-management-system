@extends('layouts.admin')

@section('title', 'Employee approvals')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
    <div><h1 class="text-2xl font-bold text-[#173F32]">Employee access approvals</h1><p class="mt-1 text-sm text-[#69736B]">Only the active Shop Owner can approve operational employee access.</p></div>
    <div class="space-y-4">
        @forelse($applications as $application)
            @php($offer = $application->offers->first())
            @if($offer && $offer->employee)
                <article class="rounded-2xl border bg-white p-5 sm:p-7"><div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-lg font-semibold">{{ $offer->employee->name }}</h2><p class="mt-1 text-sm text-gray-600">{{ $offer->employee->email }} · {{ $offer->position_type === 'manager' ? 'Branch Manager' : 'Employee Seller' }}</p><p class="mt-1 text-sm text-gray-600">{{ $application->posting->title }} · assigned to {{ $offer->branch->name }}</p><p class="mt-3 text-sm text-gray-700">{{ $offer->employment_terms }}</p></div><div class="flex gap-2"><form method="POST" action="{{ route('admin.careers.approvals.approve', $offer) }}">@csrf<button class="rounded-lg bg-[#245C45] px-4 py-2.5 text-sm font-semibold text-white">Approve access</button></form><form method="POST" action="{{ route('admin.careers.approvals.reject', $offer) }}">@csrf<button class="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700">Decline</button></form></div></div></article>
            @endif
        @empty
            <div class="rounded-2xl border border-dashed bg-white p-9 text-sm text-gray-600">No accepted offers are waiting for Owner approval.</div>
        @endforelse
    </div>
</div>
@endsection
