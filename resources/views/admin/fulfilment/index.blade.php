@extends('layouts.admin')

@section('title', 'Order Fulfilment')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Order Fulfilment</h1>
        <p class="mt-1 text-xs text-[#69736B]">Branch-scoped preparation, dispatch, pickup, and delivery operations. Order totals do not imply payment collection.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            \App\Enums\FulfilmentStatus::AwaitingConfirmation,
            \App\Enums\FulfilmentStatus::Confirmed,
            \App\Enums\FulfilmentStatus::Picking,
            \App\Enums\FulfilmentStatus::ShortageReview,
            \App\Enums\FulfilmentStatus::Packed,
            \App\Enums\FulfilmentStatus::ReadyForPickup,
            \App\Enums\FulfilmentStatus::ReadyForDispatch,
            \App\Enums\FulfilmentStatus::Dispatched,
            \App\Enums\FulfilmentStatus::OutForDelivery,
            \App\Enums\FulfilmentStatus::DeliveryFailed,
            \App\Enums\FulfilmentStatus::ReturnedToSender,
            \App\Enums\FulfilmentStatus::Delivered,
        ] as $status)
            <a href="{{ route('admin.fulfilment.index', ['status' => $status->value]) }}" class="rounded-xl border border-[#245C45]/15 bg-white p-3 shadow-sm hover:border-[#245C45]/40">
                <p class="text-[11px] font-semibold text-[#69736B]">{{ $status->label() }}</p>
                <p class="mt-1 text-xl font-bold text-[#173F32]">{{ $counts[$status->value] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    @if(($counts[\App\Enums\FulfilmentStatus::ShortageReview->value] ?? 0) > 0 || ($counts[\App\Enums\FulfilmentStatus::DeliveryFailed->value] ?? 0) > 0 || ($counts[\App\Enums\FulfilmentStatus::ReturnedToSender->value] ?? 0) > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900">
            Orders with an item availability issue, failed delivery, or returned parcel require staff review. These states do not automatically cancel, refund, or release stock.
        </div>
    @endif

    <form method="GET" action="{{ route('admin.fulfilment.index') }}" class="grid gap-3 rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-5">
        <label class="text-[11px] font-semibold text-[#69736B]">Fulfilment stage
            <select name="status" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                <option value="">All stages</option>
                @foreach(\App\Enums\FulfilmentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-[11px] font-semibold text-[#69736B]">From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"></label>
        <label class="text-[11px] font-semibold text-[#69736B]">To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"></label>
        @if($membership->role === \App\Services\BusinessAccessService::OWNER)
            <label class="text-[11px] font-semibold text-[#69736B]">Branch
                <select name="branch_id" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select>
            </label>
        @endif
        <div class="flex items-end gap-2"><button class="w-full rounded-lg bg-[#245C45] px-3 py-2 text-xs font-semibold text-white">Apply filters</button><a href="{{ route('admin.fulfilment.index') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">Clear</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($orders->isEmpty())
            <p class="p-8 text-center text-xs text-[#69736B]">No orders match these fulfilment filters.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[10px] font-bold uppercase tracking-wider text-[#69736B]"><tr><th class="px-4 py-3">Order</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Delivery</th><th class="px-4 py-3 text-right">Order value</th><th class="px-4 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($orders as $order)
                            <tr>
                                <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-semibold text-[#245C45] hover:underline">{{ $order->order_number }}</a><div class="mt-1 text-[10px] text-[#69736B]">{{ $order->created_at?->format('M j, Y g:i A') }}</div></td>
                                <td class="px-4 py-3 font-semibold text-[#202820]">{{ $order->customer_name }}</td>
                                <td class="px-4 py-3 text-[#69736B]">{{ $order->branch?->name ?? 'Company-wide order' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-blue-50 px-2 py-1 font-semibold text-blue-800">{{ $order->fulfilment_status->label() }}</span></td>
                                <td class="px-4 py-3 text-[#69736B]">{{ $order->delivery_method?->label() ?? 'Not selected' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-[#245C45] hover:underline">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())<div class="border-t border-gray-100 p-4">{{ $orders->links() }}</div>@endif
        @endif
    </div>
</div>
@endsection
