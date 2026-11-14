@extends('emails.layout')

@section('content')
    <h2 class="heading">Security Alert: Account Updated</h2>
    
    <p class="text">
        Hello <strong>{{ $user->name }}</strong>,
    </p>

    <p class="text">
        An important update has been made to your PawHaven account:
    </p>

    <div class="info-card" style="border-left: 4px solid #245C45;">
        <div style="font-weight: 700; color: #173F32; margin-bottom: 4px;">{{ $activityTitle }}</div>
        <div style="color: #4A564C;">{{ $activityDescription }}</div>
        <div style="margin-top: 8px; font-size: 11px; color: #69736B;">Timestamp: {{ now()->toFormattedDateString() }} at {{ now()->format('H:i T') }}</div>
    </div>

    <p class="text">
        If you authorized this change, no further action is required. If you did not make or authorize this update, please reset your password and contact platform administration immediately.
    </p>
@endsection

