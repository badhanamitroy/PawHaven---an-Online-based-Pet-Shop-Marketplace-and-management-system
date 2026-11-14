@extends('emails.layout')

@section('content')
    <h1>Verify your offer email</h1>
    <p>Use this code to verify the email address on your PawHaven employment offer:</p>
    <p style="font-size:28px;font-weight:700;letter-spacing:8px">{{ $code }}</p>
    <p>This code expires in {{ $expiryMinutes }} minutes. PawHaven staff will never ask you to share it.</p>
@endsection
