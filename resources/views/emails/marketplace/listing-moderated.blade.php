@extends('emails.layout')

@section('content')
    <h2 class="heading">Listing Review Update</h2>
    
    <p class="text">
        Hello <strong>{{ $listing->seller->name ?? 'Pet Owner' }}</strong>,
    </p>

    <p class="text">
        Our moderation team has completed reviewing your pet listing: <strong>&ldquo;{{ $listing->title }}&rdquo;</strong>.
    </p>

    @if($isApproved)
        <div class="info-card" style="border-left: 4px solid #10B981; background-color: #ECFDF5;">
            <div style="font-size: 16px; font-weight: 800; color: #065F46; margin-bottom: 4px;">
                ✓ Approved &amp; Published
            </div>
            <div style="font-size: 13px; color: #047857;">
                Your listing is now live on the PawHaven Pet Marketplace for adopters and pet lovers to see.
            </div>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ route('public.home') }}#find-pets" class="btn">View on Marketplace &rarr;</a>
        </div>
    @else
        <div class="info-card" style="border-left: 4px solid #EF4444; background-color: #FEF2F2;">
            <div style="font-size: 16px; font-weight: 800; color: #991B1B; margin-bottom: 4px;">
                ✕ Listing Not Approved
            </div>
            <div style="font-size: 13px; color: #B91C1C;">
                Your listing could not be published because it does not meet our ethical pet guidelines.
            </div>
            @if($reason)
                <div style="margin-top: 8px; font-size: 12px; color: #7F1D1D; background: #ffffff; padding: 10px; border-radius: 8px;">
                    <strong>Reason / Moderator Note:</strong> {{ $reason }}
                </div>
            @endif
        </div>
    @endif

    <p class="text" style="font-size: 13px; color: #69736B; margin-top: 24px;">
        PawHaven enforces strict animal welfare standards to protect domestic pets and buyers.
    </p>
@endsection

