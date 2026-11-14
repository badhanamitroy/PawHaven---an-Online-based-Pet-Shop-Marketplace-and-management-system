@props([
    'sidebar' => false,
])

@php($brandName = \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven')))

@if($sidebar)
    <flux:sidebar.brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-white p-1 shadow-sm border border-black/10">
            <x-app-logo-icon class="size-6 object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-white p-1 shadow-sm border border-black/10">
            <x-app-logo-icon class="size-6 object-contain" />
        </x-slot>
    </flux:brand>
@endif
