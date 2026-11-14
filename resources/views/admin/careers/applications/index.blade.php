@extends('layouts.admin')

@section('title', 'Careers applications')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
    <div><h1 class="text-2xl font-bold text-[#173F32]">Careers applications</h1><p class="mt-1 text-sm text-[#69736B]">Candidate details and notes are visible only to authorized recruitment admins.</p></div>
    <form method="GET" class="flex flex-wrap gap-3 rounded-2xl border bg-white p-4"><select name="vacancy" class="rounded-lg border-gray-300 text-sm"><option value="">All vacancies</option>@foreach($vacancies as $vacancy)<option value="{{ $vacancy->id }}" @selected(request('vacancy') == $vacancy->id)>{{ $vacancy->title }}</option>@endforeach</select><select name="status" class="rounded-lg border-gray-300 text-sm"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select><button class="rounded-lg bg-[#245C45] px-4 py-2 text-sm font-semibold text-white">Filter</button></form>
    <div class="overflow-hidden rounded-2xl border bg-white">
        @forelse($applications as $application)
            <article class="flex flex-col justify-between gap-3 border-b p-5 last:border-0 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-gray-900">{{ $application->full_name }}</h2><p class="text-sm text-gray-600">{{ $application->posting->title }} · {{ $application->email }}</p><p class="mt-1 text-xs text-gray-500">{{ str_replace('_', ' ', ucfirst($application->status)) }} · received {{ $application->submitted_at->format('M j, Y') }}</p></div><a href="{{ route('admin.careers.applications.show', $application) }}" class="rounded-lg border px-4 py-2 text-sm font-semibold">Review</a></article>
        @empty<p class="p-8 text-sm text-gray-600">No applications match these filters.</p>@endforelse
    </div>
    {{ $applications->links() }}
</div>
@endsection
