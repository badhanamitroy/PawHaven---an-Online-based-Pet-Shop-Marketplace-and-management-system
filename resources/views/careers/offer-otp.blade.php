@extends('layouts.careers')

@section('title', 'Verify offer email')

@section('content')
    <section class="mx-auto max-w-xl rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-sm sm:p-10">
        <p class="text-sm font-semibold uppercase tracking-wide text-[#245C45]">Email verification</p>
        <h1 class="mt-2 text-2xl font-bold text-[#173F32]">Enter your verification code</h1>
        <p class="mt-3 text-sm leading-6 text-[#69736B]">We sent a 6-digit code to the email address on your offer. The code expires in 10 minutes. This step does not sign you in.</p>
        <form method="POST" action="{{ route('careers.offers.verify.submit') }}" class="mt-6 space-y-4">@csrf
            <label class="block text-sm font-medium">Verification code<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="mt-1 block w-full rounded-xl border-gray-300 text-center text-2xl tracking-[0.4em]" /></label>
            <button class="w-full rounded-xl bg-[#245C45] px-6 py-3 text-sm font-semibold text-white hover:bg-[#173F32]">Verify and continue</button>
        </form>
        <form method="POST" action="{{ route('careers.offers.verify.resend') }}" class="mt-4 text-center">@csrf<button class="text-sm font-semibold text-[#245C45] underline">Send a new code</button></form>
        <p class="mt-4 text-xs leading-5 text-[#69736B]">If this browser session was interrupted, reopen the original offer link to begin again.</p>
    </section>
@endsection
