@extends('layouts.careers')

@section('title', 'Careers')

@section('content')
    <section class="rounded-3xl bg-[#173F32] px-6 py-10 text-white sm:px-10 sm:py-14">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#D9E9D8]">Work with PawHaven</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Careers and opportunities</h1>
        <p class="mt-4 max-w-2xl text-sm leading-6 text-white/80 sm:text-base">Explore current openings with our team. You can apply directly without creating an account.</p>
    </section>

    <section class="mt-10" aria-labelledby="open-roles">
        <div class="mb-5 flex items-end justify-between gap-3">
            <div><p class="text-sm font-semibold uppercase tracking-wide text-[#245C45]">Current openings</p><h2 id="open-roles" class="mt-1 text-2xl font-bold text-[#173F32]">Find your next role</h2></div>
        </div>
        @forelse($vacancies as $vacancy)
            <article class="mb-4 rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-[#69736B]">
                            <span class="rounded-full bg-[#245C45]/10 px-3 py-1 text-[#245C45]">{{ $vacancy->position_type === 'manager' ? 'Branch Manager' : 'Seller / Sales Associate' }}</span>
                            @if($vacancy->employment_type)<span>{{ $vacancy->employment_type }}</span>@endif
                            @if($vacancy->application_deadline)<span>Apply by {{ $vacancy->application_deadline->format('M j, Y') }}</span>@endif
                        </div>
                        <h3 class="mt-3 text-xl font-bold text-[#173F32]">{{ $vacancy->title }}</h3>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-[#586158]">{{ $vacancy->summary }}</p>
                        @if($vacancy->branches->isNotEmpty())
                            <p class="mt-4 text-sm text-[#69736B]"><span class="font-semibold text-[#202820]">Locations:</span> {{ $vacancy->branches->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                    <a href="{{ route('careers.show', $vacancy->slug) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-[#245C45] px-5 py-3 text-sm font-semibold text-white hover:bg-[#173F32]">View details &amp; apply</a>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-[#202820]/20 bg-white p-10 text-center">
                <h3 class="text-lg font-semibold text-[#173F32]">No open positions right now</h3>
                <p class="mt-2 text-sm text-[#69736B]">Please check back later for new PawHaven opportunities.</p>
            </div>
        @endforelse
        <div class="mt-6">{{ $vacancies->links() }}</div>
    </section>
@endsection
