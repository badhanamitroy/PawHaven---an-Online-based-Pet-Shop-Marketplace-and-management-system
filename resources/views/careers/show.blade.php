@extends('layouts.careers')

@section('title', $vacancy->title)

@section('content')
    <a href="{{ route('careers.index') }}" class="text-sm font-semibold text-[#245C45] hover:underline">← All openings</a>
    <article class="mt-4 rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-sm sm:p-10">
        <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-[#69736B]">
            <span class="rounded-full bg-[#245C45]/10 px-3 py-1 text-[#245C45]">{{ $vacancy->position_type === 'manager' ? 'Branch Manager' : 'Seller / Sales Associate' }}</span>
            @if($vacancy->employment_type)<span class="rounded-full bg-[#202820]/5 px-3 py-1">{{ $vacancy->employment_type }}</span>@endif
        </div>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-[#173F32]">{{ $vacancy->title }}</h1>
        <p class="mt-3 text-base leading-7 text-[#586158]">{{ $vacancy->summary }}</p>
        <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-sm text-[#69736B]">
            @if($vacancy->branches->isNotEmpty())<p><strong class="text-[#202820]">Locations:</strong> {{ $vacancy->branches->pluck('name')->join(', ') }}</p>@endif
            <p><strong class="text-[#202820]">Openings:</strong> {{ $vacancy->openings }}</p>
            @if($vacancy->application_deadline)<p><strong class="text-[#202820]">Deadline:</strong> {{ $vacancy->application_deadline->format('M j, Y') }}</p>@endif
        </div>
        <div class="prose prose-stone mt-8 max-w-none whitespace-pre-line text-sm leading-7 text-[#343B35]"><h2 class="text-lg font-bold text-[#173F32]">About the role</h2>{{ $vacancy->description }}</div>
        @if($vacancy->responsibilities)<div class="mt-7"><h2 class="text-lg font-bold text-[#173F32]">Responsibilities</h2><div class="mt-2 whitespace-pre-line text-sm leading-7 text-[#343B35]">{{ $vacancy->responsibilities }}</div></div>@endif
        @if($vacancy->requirements)<div class="mt-7"><h2 class="text-lg font-bold text-[#173F32]">Requirements</h2><div class="mt-2 whitespace-pre-line text-sm leading-7 text-[#343B35]">{{ $vacancy->requirements }}</div></div>@endif
    </article>

    @if(session('application_submitted'))
        <div role="status" class="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900">{{ session('application_submitted') }}</div>
    @else
        <section id="apply" class="mt-8 rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-sm sm:p-10">
            <h2 class="text-2xl font-bold text-[#173F32]">Apply for this position</h2>
            <p class="mt-2 text-sm text-[#69736B]">No account is needed. The preferred location is only a preference; PawHaven confirms any final branch in a formal offer.</p>
            <form method="POST" action="{{ route('careers.apply', $vacancy->slug) }}" enctype="multipart/form-data" class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                @csrf
                <label class="text-sm font-medium">Full name<input name="full_name" value="{{ old('full_name') }}" required maxlength="150" autocomplete="name" class="mt-1 block w-full rounded-xl border-gray-300" /></label>
                <label class="text-sm font-medium">Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" class="mt-1 block w-full rounded-xl border-gray-300" /></label>
                <label class="text-sm font-medium">Phone<input name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel" class="mt-1 block w-full rounded-xl border-gray-300" /></label>
                @if($vacancy->branches->isNotEmpty())
                    <label class="text-sm font-medium">Preferred location (optional)<select name="preferred_branch_id" class="mt-1 block w-full rounded-xl border-gray-300"><option value="">No preference</option>@foreach($vacancy->branches as $branch)<option value="{{ $branch->id }}" @selected(old('preferred_branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                @endif
                <label class="text-sm font-medium sm:col-span-2">Current address (optional)<textarea name="address" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border-gray-300">{{ old('address') }}</textarea></label>
                <label class="text-sm font-medium sm:col-span-2">Relevant experience (optional)<textarea name="experience" rows="4" maxlength="10000" class="mt-1 block w-full rounded-xl border-gray-300">{{ old('experience') }}</textarea></label>
                <label class="text-sm font-medium sm:col-span-2">Why are you interested?<textarea name="motivation" rows="4" required maxlength="10000" class="mt-1 block w-full rounded-xl border-gray-300">{{ old('motivation') }}</textarea></label>
                <label class="text-sm font-medium sm:col-span-2">Resume or CV (PDF, DOC, DOCX; up to 5 MB)<input name="cv" type="file" accept=".pdf,.doc,.docx" class="mt-1 block w-full rounded-xl border border-gray-300 p-2 text-sm" /></label>
                <div class="sm:col-span-2"><button class="rounded-xl bg-[#245C45] px-6 py-3 text-sm font-semibold text-white hover:bg-[#173F32]">Submit application</button></div>
            </form>
        </section>
    @endif
@endsection
