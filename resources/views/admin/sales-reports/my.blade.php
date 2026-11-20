@extends('layouts.admin')

@section('title', 'My Sales')

@section('content')
<div class="space-y-5">
    <div><h1 class="text-2xl font-bold tracking-tight text-[#173F32]">My Sales</h1><p class="mt-1 text-sm text-[#69736B]">Orders attributed to your active Employee Seller account.</p></div>
    @include('admin.sales-reports.partials.filters', ['routeName' => 'admin.sales-reports.my'])
    @include('admin.sales-reports.partials.summary')
    @include('admin.sales-reports.partials.orders', ['showEmployee' => false, 'showBranch' => false, 'detailRoute' => 'admin.sales-reports.my.show'])
</div>
@endsection
