@extends('emails.layout')

@section('content')
    <h2 class="heading">Buy Request Received! 🛍️</h2>
    
    <p class="text">
        Hello <strong>{{ $order->customer_name }}</strong>,
    </p>

    <p class="text">
        Thank you for shopping at PawHaven. Your direct buy request has been recorded and submitted to our store team for verification.
    </p>

    <div class="info-card">
        <div style="font-size: 16px; font-weight: 800; color: #173F32; margin-bottom: 8px;">
            Order #{{ $order->order_number }}
        </div>
        <div><strong>Status:</strong> <span style="color: #D97706; font-weight: 700;">{{ $order->status->label() }}</span></div>
        <div><strong>Payment Method:</strong> Cash on Delivery (COD)</div>
        <div><strong>Total Amount:</strong> ${{ number_format($order->total_minor / 100, 2) }}</div>
        <div><strong>Delivery Address:</strong> {{ $order->shipping_address }}, {{ $order->shipping_city }}</div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('dashboard') }}" class="btn">View Order in Dashboard &rarr;</a>
    </div>

    <p class="text" style="font-size: 13px; color: #69736B;">
        Our shopkeeper will contact your phone number ({{ $order->customer_phone }}) if any delivery confirmations are required.
    </p>
@endsection

