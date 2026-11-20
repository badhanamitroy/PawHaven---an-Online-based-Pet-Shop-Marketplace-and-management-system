@extends('emails.layout')

@section('content')
    <h2 class="heading">Payment Status Updated</h2>
    
    <p class="text">
        Hello <strong>{{ $order->customer_name }}</strong>,
    </p>

    <p class="text">
        We have an update regarding the payment for your order <strong>#{{ $order->order_number }}</strong>:
    </p>

    <div class="info-card" style="border-left: 4px solid #10B981;">
        <div style="font-size: 16px; font-weight: 800; color: #173F32; margin-bottom: 4px;">
            Payment Status: {{ $order->payment_status->label() }}
        </div>
        <div><strong>Total Amount:</strong> {{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}</div>
        <div><strong>Order Status:</strong> {{ $order->status->label() }}</div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('dashboard') }}" class="btn">View in Dashboard &rarr;</a>
    </div>
@endsection

