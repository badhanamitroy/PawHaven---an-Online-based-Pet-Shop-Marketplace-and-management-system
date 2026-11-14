@extends('emails.layout')

@section('content')
    <div style="margin-bottom: 24px;">
        <span style="display: inline-block; background-color: rgba(36, 92, 69, 0.1); color: #245C45; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 4px 12px; border-radius: 9999px;">
            Official Management Appointment
        </span>
    </div>

    <h2 class="heading">
        @if(!empty($invitation->name))
            Dear {{ $invitation->name }},
        @else
            Dear Colleague,
        @endif
    </h2>

    <p class="text">
        PawHaven Administration is pleased to officially invite you to join our operational team as the <strong>Branch Manager</strong> for the <strong>{{ $branch->name }}</strong> branch.
    </p>

    <p class="text">
        In this role, you will hold operational authority over your assigned branch, including managing the branch's product catalog, overseeing inventory levels, fulfilling customer orders, and upholding PawHaven's ethical care standards.
    </p>

    <div class="info-card">
        <div style="margin-bottom: 8px;"><strong>Position:</strong> <span style="color: #245C45; font-weight: 700;">Branch Manager</span></div>
        <div style="margin-bottom: 8px;"><strong>Assigned Branch:</strong> <span style="color: #173F32; font-weight: 700;">{{ $branch->name }} ({{ $branch->code }})</span></div>
        <div style="margin-bottom: 8px;"><strong>Authorized Email:</strong> {{ $invitation->email }}</div>
        <div style="margin-bottom: 8px;"><strong>Invitation Date:</strong> {{ $invitation->created_at->format('F j, Y') }}</div>
        <div><strong>Invitation Valid Until:</strong> {{ $invitation->expires_at->format('F j, Y, g:i A T') }}</div>
    </div>

    <p class="text">
        To complete your management registration and set up your secure staff credentials, please click the link below:
    </p>

    <div style="text-align: center; margin: 32px 0;">
        <a href="{{ route('invitations.register', ['token' => $rawToken]) }}" class="btn">
            Accept Appointment &amp; Complete Registration &rarr;
        </a>
    </div>

    <div style="background-color: #FAF8F2; border-left: 3px solid #F28C75; padding: 12px 16px; margin: 24px 0; font-size: 12px; color: #69736B;">
        <strong>Security Notice:</strong> This appointment link is personalized, cryptographically signed, and single-use. It expires automatically in 72 hours. Do not forward this email or share your link with anyone.
    </div>

    <div style="margin-top: 36px; padding-top: 20px; border-top: 1px solid rgba(32, 40, 32, 0.08); font-size: 13px; color: #4A564C;">
        <div>Sincerely,</div>
        <div style="font-weight: 700; color: #173F32; margin-top: 4px;">PawHaven Administration</div>
        <div style="font-size: 11px; color: #69736B;">Office of Human Resources &amp; Store Operations</div>
    </div>
@endsection
