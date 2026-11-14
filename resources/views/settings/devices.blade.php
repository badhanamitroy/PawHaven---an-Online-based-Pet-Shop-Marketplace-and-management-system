<x-layouts::app.sidebar :title="__('Active devices')">
    <div class="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#69736B]">Account security</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#173F32]">Active devices</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#69736B]">
                    Each browser profile is tracked as a separate sign-in. Device details are approximate and are not used to identify or authenticate you.
                </p>
            </div>
            <form method="POST" action="{{ route('devices.revoke-others') }}">
                @csrf
                @method('DELETE')
                <button class="rounded-xl border border-[#245C45]/20 px-4 py-2.5 text-sm font-semibold text-[#245C45] hover:bg-[#245C45]/5">
                    Log out all other devices
                </button>
            </form>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-[#202820]/10 bg-white">
            <div class="divide-y divide-[#202820]/10">
                @forelse($devices as $device)
                    @php
                        $expired = $device->expires_at->isPast()
                            || $device->last_activity_at->lt(now()->subDays(config('device_sessions.idle_days', 30)));
                        $active = $device->revoked_at === null && ! $expired;
                        $isCurrent = $device->id === $currentDeviceId;
                    @endphp
                    <article class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-[#202820]">{{ $device->device_name }}</h2>
                                @if($isCurrent && $active)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-800">This device</span>
                                @endif
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $active ? 'bg-emerald-50 text-emerald-800' : 'bg-zinc-100 text-zinc-600' }}">
                                    {{ $active ? 'Active' : ($device->revoked_at ? 'Signed out' : 'Expired') }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-[#69736B]">
                                {{ $device->browser_name ?? 'Unknown browser' }}{{ $device->browser_version ? ' '.$device->browser_version : '' }}
                                · {{ $device->platform ?? 'Unknown platform' }} · {{ ucfirst($device->device_type) }}
                            </p>
                            <dl class="mt-3 grid gap-x-8 gap-y-1 text-xs text-[#69736B] sm:grid-cols-2">
                                <div><dt class="inline font-semibold">First sign-in: </dt><dd class="inline">{{ $device->created_at->format('M j, Y H:i T') }}</dd></div>
                                <div><dt class="inline font-semibold">Last activity: </dt><dd class="inline">{{ $device->last_activity_at->diffForHumans() }}</dd></div>
                                <div><dt class="inline font-semibold">Approximate IP: </dt><dd class="inline">{{ $device->ip_address ? \Illuminate\Support\Str::mask($device->ip_address, '•', max(0, strlen($device->ip_address) - 4)) : 'Not available' }}</dd></div>
                            </dl>
                        </div>
                        @if($active)
                            <form method="POST" action="{{ route('devices.revoke', $device->id) }}" class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                    Log out this device
                                </button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="p-6 text-sm text-[#69736B]">No device sign-ins have been recorded.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5 sm:p-6">
            <h2 class="font-semibold text-rose-900">Log out all devices</h2>
            <p class="mt-1 text-sm text-rose-800">This signs out this browser and revokes every other active device session.</p>
            <form method="POST" action="{{ route('devices.revoke-all') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                @method('DELETE')
                <label class="block flex-1 text-sm font-medium text-rose-900">
                    Type <span class="font-bold">LOG OUT ALL DEVICES</span> to confirm
                    <input name="confirmation" required autocomplete="off" class="mt-1 block w-full rounded-lg border-rose-300 text-sm focus:border-rose-500 focus:ring-rose-500">
                    @error('confirmation')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                </label>
                <button class="rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">
                    Log out everywhere
                </button>
            </form>
            <p class="mt-4 text-xs leading-relaxed text-rose-800">
                Approximate IP addresses are retained with device records for up to {{ config('device_sessions.retention_days', 30) }} days after expiry or revocation. They may be personal data under your local privacy rules; publish an appropriate privacy notice.
            </p>
        </section>
    </div>
</x-layouts::app.sidebar>
