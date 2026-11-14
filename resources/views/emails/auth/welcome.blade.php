@extends('emails.layout')

@section('content')
    <h2 class="heading">Welcome to PawHaven, {{ $user->name }}! 🐾</h2>
    
    <p class="text">
        Thank you for joining our community. Your account has been successfully created.
    </p>

    <div class="info-card">
        <div><strong>Account Email:</strong> {{ $user->email }}</div>
        <div><strong>Account Role:</strong> <span style="font-weight: 700; color: #245C45; text-transform: uppercase;">{{ ucfirst($user->role ?? 'Customer') }}</span></div>
    </div>

    @if($user->isSeller())
        <p class="text">
            As a registered merchant, you have access to the PawHaven Pet Shop operations console to manage catalog items and review customer buy requests.
        </p>
    @else
        <p class="text">
            You can now browse vetted pet listings, order premium pet food and accessories with Cash on Delivery, and connect with compassionate shelters.
        </p>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('public.home') }}" class="btn">Explore PawHaven &rarr;</a>
    </div>

    <p class="text" style="font-size: 13px; color: #69736B;">
        Dedicated to ethical pet parenthood and lifelong companion care.
    </p>
@endsection

