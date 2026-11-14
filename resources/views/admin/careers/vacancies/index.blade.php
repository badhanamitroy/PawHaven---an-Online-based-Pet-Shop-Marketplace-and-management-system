@extends('layouts.admin')

@section('title', 'Careers vacancies')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold text-[#173F32]">Careers vacancies</h1><p class="mt-1 text-sm text-[#69736B]">Publish and manage public job circulars.</p></div><a href="{{ route('admin.careers.vacancies.create') }}" class="rounded-xl bg-[#245C45] px-4 py-2.5 text-sm font-semibold text-white">Create vacancy</a></div>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        @forelse($vacancies as $vacancy)
            <article class="flex flex-col justify-between gap-4 border-b border-gray-100 p-5 last:border-0 sm:flex-row sm:items-center">
                <div><h2 class="font-semibold text-gray-900">{{ $vacancy->title }}</h2><p class="mt-1 text-sm text-gray-600">{{ $vacancy->position_type === 'manager' ? 'Branch Manager' : 'Seller / Sales Associate' }} · {{ ucfirst($vacancy->status) }} · {{ $vacancy->applications_count }} applications</p><p class="mt-1 text-xs text-gray-500">Public URL: {{ route('careers.show', $vacancy->slug) }}</p></div>
                <div class="flex gap-2"><a href="{{ route('admin.careers.applications.index', ['vacancy' => $vacancy->id]) }}" class="rounded-lg border px-3 py-2 text-sm">Applications</a><a href="{{ route('admin.careers.vacancies.edit', $vacancy) }}" class="rounded-lg border px-3 py-2 text-sm">Edit</a></div>
            </article>
        @empty
            <p class="p-8 text-sm text-gray-600">No vacancies have been created.</p>
        @endforelse
    </div>
    {{ $vacancies->links() }}
</div>
@endsection
