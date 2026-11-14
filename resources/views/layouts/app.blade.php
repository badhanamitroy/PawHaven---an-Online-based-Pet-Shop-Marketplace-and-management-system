<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="{{ request()->routeIs('seller.*') ? '' : 'bg-[#FAF8F2]' }}">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
