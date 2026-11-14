@extends('emails.layout')

@section('content')
    <h1>Employee access approval required</h1>
    <p>{{ $employeeName }} verified an employment offer for {{ $position }} at {{ $branchName }}.</p>
    <p>The account has no operational access until you review the assignment.</p>
    <p><a href="{{ route('admin.careers.approvals.index') }}">Review employee access</a></p>
@endsection
