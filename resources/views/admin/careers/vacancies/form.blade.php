@extends('layouts.admin')

@section('title', $vacancy->exists ? 'Edit vacancy' : 'Create vacancy')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <div><a href="{{ route('admin.careers.vacancies.index') }}" class="text-sm font-semibold text-[#245C45]">← Careers vacancies</a><h1 class="mt-2 text-2xl font-bold text-[#173F32]">{{ $vacancy->exists ? 'Edit vacancy' : 'Create vacancy' }}</h1></div>
    <form method="POST" action="{{ $vacancy->exists ? route('admin.careers.vacancies.update', $vacancy) : route('admin.careers.vacancies.store') }}" class="space-y-5 rounded-2xl border bg-white p-5 sm:p-7">
        @csrf @if($vacancy->exists) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="text-sm font-medium sm:col-span-2">Job title<input name="title" required maxlength="150" value="{{ old('title', $vacancy->title) }}" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm font-medium">Position<select name="position_type" required class="mt-1 block w-full rounded-lg border-gray-300"><option value="seller" @selected(old('position_type', $vacancy->position_type) === 'seller')>Seller / Sales Associate</option><option value="manager" @selected(old('position_type', $vacancy->position_type) === 'manager')>Branch Manager</option></select></label>
            <label class="text-sm font-medium">Status<select name="status" required class="mt-1 block w-full rounded-lg border-gray-300">@foreach(['draft','published','closed','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $vacancy->status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
            <label class="text-sm font-medium sm:col-span-2">News-style summary<textarea name="summary" required maxlength="500" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('summary', $vacancy->summary) }}</textarea></label>
            <label class="text-sm font-medium sm:col-span-2">Job description<textarea name="description" required maxlength="20000" rows="6" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('description', $vacancy->description) }}</textarea></label>
            <label class="text-sm font-medium">Responsibilities<textarea name="responsibilities" maxlength="20000" rows="5" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('responsibilities', $vacancy->responsibilities) }}</textarea></label>
            <label class="text-sm font-medium">Requirements<textarea name="requirements" maxlength="20000" rows="5" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('requirements', $vacancy->requirements) }}</textarea></label>
            <label class="text-sm font-medium">Employment type<input name="employment_type" maxlength="60" value="{{ old('employment_type', $vacancy->employment_type) }}" placeholder="Only if applicable" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm font-medium">Number of openings<input name="openings" type="number" min="1" max="999" required value="{{ old('openings', $vacancy->openings ?: 1) }}" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm font-medium">Application deadline<input name="application_deadline" type="date" value="{{ old('application_deadline', $vacancy->application_deadline?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <fieldset class="sm:col-span-2"><legend class="text-sm font-medium">Branch options</legend><p class="mt-1 text-xs text-gray-500">Candidates may express a preference from these locations. Admin chooses the final branch in any offer.</p><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($branches as $branch)<label class="flex items-center gap-2 rounded-lg border p-3 text-sm"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, old('branch_ids', $vacancy->branches->modelKeys()), false)) />{{ $branch->name }}</label>@endforeach</div></fieldset>
        </div>
        <div class="flex justify-end"><button class="rounded-xl bg-[#245C45] px-5 py-3 text-sm font-semibold text-white">Save vacancy</button></div>
    </form>
</div>
@endsection
