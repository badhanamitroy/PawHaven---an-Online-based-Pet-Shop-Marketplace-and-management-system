@extends('emails.layout')

@section('content')
    <h1>Your PawHaven employment offer</h1>
    <p>Hello,</p>
    <p>PawHaven has prepared an offer for <strong>{{ $offer->position_type === 'manager' ? 'Branch Manager' : 'Seller / Sales Associate' }}</strong> at {{ $offer->branch->name }}.</p>
    <p>Please review the offer and respond by {{ $offer->acceptance_deadline->format('M j, Y g:i A') }}.</p>
    <p><a href="{{ route('careers.offers.show', ['token' => $rawToken]) }}">Review your offer</a></p>
    <p>If you were not expecting this message, you can ignore it.</p>
@endsection
