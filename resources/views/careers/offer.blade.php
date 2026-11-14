@extends('layouts.careers')

@section('title', 'Employment offer')

@section('content')
    @if($invalid)
        <section class="mx-auto max-w-2xl rounded-3xl border border-[#202820]/10 bg-white p-8 text-center shadow-sm">
            <h1 class="text-2xl font-bold text-[#173F32]">This offer link is unavailable</h1>
            <p class="mt-3 text-sm leading-6 text-[#69736B]">The link may have expired, been replaced, or been withdrawn. Contact PawHaven recruitment if you need help.</p>
        </section>
    @else
        <section class="mx-auto max-w-3xl rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-sm sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-wide text-[#245C45]">Employment offer</p>
            <h1 class="mt-2 text-3xl font-bold text-[#173F32]">{{ $offer->application->posting->title }}</h1>
            <p class="mt-2 text-sm text-[#69736B]">Position: {{ $offer->position_type === 'manager' ? 'Branch Manager' : 'Seller / Sales Associate' }} · Assigned branch: {{ $offer->branch->name }}</p>
            <p class="mt-1 text-sm text-[#69736B]">Please respond by {{ $offer->acceptance_deadline->format('M j, Y g:i A') }}.</p>
            <div class="mt-7 rounded-2xl bg-[#FAF8F2] p-5"><h2 class="font-bold text-[#173F32]">Employment terms</h2><div class="mt-2 whitespace-pre-line text-sm leading-7">{{ $offer->employment_terms }}</div>
                @if($offer->compensation)<h3 class="mt-5 font-bold text-[#173F32]">Compensation</h3><div class="mt-2 whitespace-pre-line text-sm leading-7">{{ $offer->compensation }}</div>@endif
            </div>
            <p class="mt-5 text-sm leading-6 text-[#586158]">Accepting starts email verification. PawHaven creates the employee account only after the code is verified. The Shop Owner must then approve operational access.</p>
            <div class="mt-6 flex flex-wrap gap-3"><form method="POST" action="{{ route('careers.offers.accept', $token) }}">@csrf<button class="rounded-xl bg-[#245C45] px-6 py-3 text-sm font-semibold text-white hover:bg-[#173F32]">Accept and verify email</button></form><form method="POST" action="{{ route('careers.offers.decline', $token) }}">@csrf<button class="rounded-xl border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-700">Decline offer</button></form></div>
        </section>
    @endif
@endsection
