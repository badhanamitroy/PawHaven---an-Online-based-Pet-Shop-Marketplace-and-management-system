@extends('emails.layout')

@section('content')
    <h2 class="heading">Order Status Updated</h2>
    
    <p class="text">
        Hello <strong>{{ $order->customer_name }}</strong>,
    </p>

    <p class="text">
        The status of your PawHaven order <strong>#{{ $order->order_number }}</strong> has been updated:
    </p>

    <div class="info-card" style="border-left: 4px solid #245C45;">
        <div style="font-size: 16px; font-weight: 800; color: #173F32; margin-bottom: 4px;">
            Current Status: {{ $order->status->label() }}
        </div>
        <div><strong>Order Total:</strong> ${{ number_format($order->total_minor / 100, 2) }}</div>
        <div><strong>Payment:</strong> {{ $order->payment_status->label() }} ({{ strtoupper($order->payment_method) }})</div>

        @if($order->cancelled_reason)
            <div style="margin-top: 8px; color: #DC2626; font-size: 13px;">
                <strong>Cancellation Note:</strong> {{ $order->cancelled_reason }}
            </div>
        @endif
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('dashboard') }}" class="btn">Track Order Details &rarr;</a>
    </div>
@endsection

