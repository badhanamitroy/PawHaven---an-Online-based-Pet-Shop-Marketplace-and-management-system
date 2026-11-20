<form method="GET" action="{{ route($routeName) }}" class="grid grid-cols-1 gap-3 rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-7">
    <div><label for="filter-from" class="mb-1 block text-xs font-semibold text-[#173F32]">From</label><input id="filter-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></div>
    <div><label for="filter-to" class="mb-1 block text-xs font-semibold text-[#173F32]">To</label><input id="filter-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></div>
    <div>
        <label for="filter-status" class="mb-1 block text-xs font-semibold text-[#173F32]">Order status</label>
        <select id="filter-status" name="status" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="">All</option>
            @foreach(\App\Enums\OrderStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="filter-payment" class="mb-1 block text-xs font-semibold text-[#173F32]">Order payment status</label>
        <select id="filter-payment" name="payment_status" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="">All</option>
            @foreach(\App\Enums\PaymentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['payment_status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="filter-transaction" class="mb-1 block text-xs font-semibold text-[#173F32]">Transaction status</label>
        <select id="filter-transaction" name="transaction_status" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="">All</option>
            @foreach(\App\Enums\PaymentTransactionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['transaction_status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="filter-channel" class="mb-1 block text-xs font-semibold text-[#173F32]">Channel</label>
        <select id="filter-channel" name="channel" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="">All</option>
            @foreach(\App\Enums\SalesChannel::cases() as $channel)<option value="{{ $channel->value }}" @selected(($filters['channel'] ?? '') === $channel->value)>{{ $channel->value === 'pos' ? 'POS' : 'Online' }}</option>@endforeach
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button class="rounded-lg bg-[#245C45] px-4 py-2 text-sm font-semibold text-white hover:bg-[#173F32]">Apply</button>
        <a href="{{ route($routeName) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[#4B554D] hover:bg-gray-50">Clear</a>
    </div>
</form>
