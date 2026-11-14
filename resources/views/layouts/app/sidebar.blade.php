@php
    $isSeller = request()->routeIs('seller.*');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isSeller ? 'dark' : 'h-full bg-[#FAF8F2]' }}">
    <head>
        @include('partials.head')
        @if(!$isSeller)
            <script>
                try { localStorage.setItem('flux.appearance', 'light'); } catch (e) {}
                document.documentElement.classList.remove('dark');
                const observer = new MutationObserver(() => {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                    }
                });
                observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            </script>
        @endif
    </head>
    <body class="min-h-screen {{ $isSeller ? 'bg-white dark:bg-zinc-800' : 'customer-portal bg-[#FAF8F2] text-[#202820] font-sans antialiased selection:bg-[#245C45] selection:text-white' }}">
        <flux:sidebar sticky collapsible="mobile" class="{{ $isSeller ? 'border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900' : 'border-e border-[#202820]/10 bg-[#FAF8F2] text-[#202820]' }}">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('PawHaven Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('My Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="globe-alt" :href="route('public.home')">
                        {{ __('Public Home') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="magnifying-glass" :href="route('public.home') . '#find-pets'">
                        {{ __('Find Pets') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shopping-bag" :href="route('public.home') . '#pet-shop'">
                        {{ __('Pet Shop') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="heart" :href="route('public.home') . '#adoption-rescue'">
                        {{ __('Adoption & Rescue') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="computer-desktop" :href="route('devices.index')">
                        {{ __('Active devices') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if(auth()->user()?->isAdmin() || auth()->user()?->isStaff() || auth()->user()?->isSeller())
                    <flux:sidebar.group :heading="auth()->user()->isAdmin() ? __('Administration') : __('Shop Operations')" class="grid mt-4">
                        <flux:sidebar.item icon="shield-check" :href="route('admin.dashboard')">
                            {{ auth()->user()->isAdmin() ? __('Admin Dashboard') : __('Seller Dashboard') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden {{ $isSeller ? '' : 'border-b border-[#202820]/10 bg-[#FAF8F2]' }}">
            <flux:sidebar.toggle class="lg:hidden {{ $isSeller ? '' : 'text-[#173F32]' }}" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
