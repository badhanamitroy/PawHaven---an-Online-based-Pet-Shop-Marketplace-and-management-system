@extends('layouts.admin')

@section('title', 'Activity & Audit Logs')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Activity &amp; Audit Logs</h1>
        <p class="text-xs text-[#69736B] mt-1">Immutable security audit trail of administrative modifications, settings changes, product updates, and moderation decisions.</p>
    </div>

    {{-- Search & Filter --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search action, description, or IP..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                >
                    Filter Logs
                </button>
                @if(request()->filled('search'))
                    <a href="{{ route('admin.audit-logs.index') }}" class="text-xs text-[#69736B] hover:underline">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Audit Log Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($logs->isEmpty())
            <div class="p-8 text-center text-xs text-[#69736B]">
                No audit log records found.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Timestamp</th>
                            <th class="px-3 py-3.5">User</th>
                            <th class="px-3 py-3.5">Action Key</th>
                            <th class="px-3 py-3.5">Description</th>
                            <th class="px-3 py-3.5 font-mono text-center">IP Address</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Payload</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-sans">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3.5 pl-6 pr-3 text-[#69736B] whitespace-nowrap">
                                    {{ $log->created_at?->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap">
                                    <div class="font-bold text-[#173F32]">{{ $log->user?->name ?? 'System' }}</div>
                                    <div class="text-[10px] text-[#69736B]">{{ $log->user?->email }}</div>
                                </td>
                                <td class="px-3 py-3.5 font-mono text-[11px] font-semibold text-[#245C45]">
                                    {{ $log->action }}
                                </td>
                                <td class="px-3 py-3.5 text-[#202820]">
                                    {{ $log->description }}
                                </td>
                                <td class="px-3 py-3.5 font-mono text-[11px] text-center text-gray-500 whitespace-nowrap">
                                    {{ $log->ip_address ?? '127.0.0.1' }}
                                </td>
                                <td class="py-3.5 pl-3 pr-6 text-right whitespace-nowrap">
                                    @if($log->payload)
                                        <details class="relative inline-block text-left">
                                            <summary class="cursor-pointer font-mono text-[11px] text-[#245C45] hover:underline list-none">
                                                [View JSON]
                                            </summary>
                                            <div class="absolute right-0 top-full mt-1 z-20 w-80 rounded-xl border border-gray-200 bg-gray-900 p-3 text-[10px] text-gray-100 shadow-xl overflow-x-auto font-mono">
                                                <pre>{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                        </details>
                                    @else
                                        <span class="text-gray-400 text-[10px]">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

