<x-layouts::admin title="System & Security Health" breadcrumbs="System Health">
    <div class="space-y-8">
        {{-- Header Card --}}
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Platform Architecture</span>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-1">System &amp; Security Health</h1>
                    <p class="text-xs sm:text-sm text-[#69736B] mt-1">
                        Inspect self-hosted environment connectivity, storage links, authentication status, and production readiness.
                    </p>
                </div>
                <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                    <span>Self-Hosted &amp; Independent</span>
                </div>
            </div>
        </div>

        {{-- Status Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {{-- Application Stack --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Application Stack</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#245C45]/10 text-[#245C45]">
                        Laravel {{ $checks['app']['laravel_version'] }}
                    </span>
                </div>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">PHP Version</dt>
                        <dd class="font-semibold text-[#173F32]">{{ $checks['app']['php_version'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Environment</dt>
                        <dd class="font-semibold capitalize text-[#173F32]">{{ $checks['app']['env'] }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-[#69736B]">Debug Mode</dt>
                        <dd>
                            @if($checks['app']['debug'])
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Enabled (local dev)</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Disabled (production ready)</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">App Timezone</dt>
                        <dd class="font-semibold text-[#173F32]">{{ $checks['app']['timezone'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">App Locale</dt>
                        <dd class="font-semibold text-[#173F32]">{{ strtoupper($checks['app']['locale']) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Database Connectivity --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Database Engine</span>
                    @if($checks['database']['connected'])
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Connected
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                            Disconnected
                        </span>
                    @endif
                </div>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Driver</dt>
                        <dd class="font-semibold uppercase text-[#173F32]">{{ $checks['database']['driver'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Schema Tables</dt>
                        <dd class="font-semibold text-[#173F32]">{{ $checks['database']['tables'] }} tables active</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Zero SaaS Storage</dt>
                        <dd class="font-semibold text-emerald-700">100% Local / Self-Hosted</dd>
                    </div>
                    @if($checks['database']['error'])
                        <div class="p-2 rounded bg-rose-50 text-rose-800 text-[11px]">
                            {{ $checks['database']['error'] }}
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Public Storage Link --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Media Storage</span>
                    @if($checks['storage']['linked'])
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Symlink Active
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                            Symlink Missing
                        </span>
                    @endif
                </div>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Filesystem Disk</dt>
                        <dd class="font-semibold uppercase text-[#173F32]">{{ $checks['storage']['disk'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Directory Writable</dt>
                        <dd class="font-semibold {{ $checks['storage']['writable'] ? 'text-emerald-700' : 'text-rose-600' }}">
                            {{ $checks['storage']['writable'] ? 'Yes' : 'Read-Only' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Public Assets</dt>
                        <dd class="font-semibold text-[#173F32]">/storage &rarr; storage/app/public</dd>
                    </div>
                </dl>
            </div>

            {{-- Security & Authentication --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Security &amp; Auth</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#245C45]/10 text-[#245C45]">
                        Fortify 2FA
                    </span>
                </div>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Admin 2FA</dt>
                        <dd class="font-semibold text-emerald-700">Enforced (Email OTP)</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Session Driver</dt>
                        <dd class="font-semibold uppercase text-[#173F32]">{{ $checks['security']['session_driver'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Session Lifetime</dt>
                        <dd class="font-semibold text-[#173F32]">{{ $checks['security']['session_lifetime'] }} minutes</dd>
                    </div>
                </dl>
            </div>

            {{-- Mail Delivery --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Mail Services</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-sky-50 text-sky-700 border border-sky-200">
                        {{ strtoupper($checks['mail']['mailer']) }}
                    </span>
                </div>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">SMTP Status</dt>
                        <dd class="font-semibold text-[#173F32]">{{ $checks['mail']['host'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Sender Address</dt>
                        <dd class="font-semibold text-[#173F32] truncate max-w-[150px]">{{ $checks['mail']['from_address'] ?? 'Default' }}</dd>
                    </div>
                    <div class="text-[11px] text-[#69736B] pt-1 border-t border-[#202820]/5">
                        Passwords and SMTP keys are never exposed in administrative views.
                    </div>
                </dl>
            </div>

            {{-- Cache & Performance --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Cache &amp; Queues</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Self-Hosted
                    </span>
                </div>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Cache Driver</dt>
                        <dd class="font-semibold uppercase text-[#173F32]">{{ $checks['cache_queue']['cache_driver'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Queue Driver</dt>
                        <dd class="font-semibold uppercase text-[#173F32]">{{ $checks['cache_queue']['queue_driver'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#69736B]">Remote License Check</dt>
                        <dd class="font-semibold text-emerald-700">None (100% Free / Unlocked)</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Production Readiness Guidance --}}
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-4">
            <h2 class="text-lg font-bold text-[#173F32]">Production Readiness Checklist</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="flex items-start gap-3 p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">✓</span>
                    <div>
                        <span class="font-bold text-[#173F32]">Independent Self-Hosting</span>
                        <p class="text-[#69736B] mt-0.5">PawHaven runs entirely on the buyer's server. No remote activation calls, external telemetry, or vendor lock-in.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">✓</span>
                    <div>
                        <span class="font-bold text-[#173F32]">Strict Access Control</span>
                        <p class="text-[#69736B] mt-0.5">All administrative operations require staff or admin privileges. Sellers and customers cannot escalate roles or access governance.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">✓</span>
                    <div>
                        <span class="font-bold text-[#173F32]">Audit Logging</span>
                        <p class="text-[#69736B] mt-0.5">Every administrative change to brand settings, homepage layout, moderation, and user roles is permanently recorded.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">✓</span>
                    <div>
                        <span class="font-bold text-[#173F32]">Static Analysis Standard</span>
                        <p class="text-[#69736B] mt-0.5">Application code passes strict PHPStan Level 7 static analysis and Laravel Pint PSR-12 code style standards.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::admin>

