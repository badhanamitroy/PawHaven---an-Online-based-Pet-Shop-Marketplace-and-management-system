<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Careers') · PawHaven</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#FAF8F2] text-[#202820] antialiased">
    <header class="border-b border-[#202820]/10 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('public.home') }}" class="text-xl font-bold text-[#173F32]">PawHaven</a>
            <nav aria-label="Careers navigation" class="flex items-center gap-5 text-sm font-medium">
                <a href="{{ route('public.home') }}" class="text-[#69736B] hover:text-[#245C45]">Home</a>
                <a href="{{ route('careers.index') }}" class="text-[#245C45]">Careers</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        @if(session('success'))
            <div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                <p class="font-semibold">Please review the information and try again.</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="border-t border-[#202820]/10 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-[#69736B] sm:px-6">PawHaven · Careers</div>
    </footer>
</body>
</html>
