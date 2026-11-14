@extends('emails.layout')

@section('content')
    <h2 class="heading">Login Verification Code</h2>
    
    <p class="text">
        Hello <strong>{{ $user->name }}</strong>,
    </p>

    <p class="text">
        We received a sign-in attempt for your PawHaven account (<strong>{{ $user->email }}</strong>). Please use the single-use verification code below to complete your authentication:
    </p>

    <div class="otp-box">
        <div class="otp-code">{{ $otp }}</div>
        <p class="otp-note">Valid for <strong>5 minutes</strong>. Never share this code with anyone.</p>
    </div>

    <div class="info-card">
        <strong>Security Tip:</strong> PawHaven staff will never contact you asking for your verification code or password. If you did not initiate this sign-in request, please secure your password immediately.
    </div>

    <p class="text" style="font-size: 13px; color: #69736B; margin-top: 24px;">
        Account Role: <span style="font-weight: 700; color: #173F32; text-transform: uppercase;">{{ ucfirst($user->role ?? 'Customer') }}</span>
    </p>
@endsection

