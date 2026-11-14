<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-full font-sans antialiased bg-[#FAF8F2] text-[#202820]">
        @php
            $brandName = \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven'));
            $rawLogo = \App\Facades\Settings::get('brand.logo_light_path', 'images/pawhaven-logo-green.png');
            $greenLogo = str_starts_with((string) $rawLogo, 'http') ? $rawLogo : '/' . ltrim((string) $rawLogo, '/');
        @endphp

        <div class="flex min-h-screen flex-col items-center justify-center p-6 sm:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('public.home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <img src="{{ $greenLogo }}" alt="{{ $brandName }}" class="h-10 w-auto max-h-10 object-contain" />
                    <span class="text-xl font-bold tracking-tight text-[#173F32]">{{ $brandName }}</span>
                </a>

                <div class="rounded-3xl border border-[#202820]/10 bg-white p-8 sm:p-10 shadow-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
