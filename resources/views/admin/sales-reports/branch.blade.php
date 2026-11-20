@extends('layouts.admin')

@section('title', 'Branch Sales')

@section('content')
<div class="space-y-5">
    <div><h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Branch Sales</h1><p class="mt-1 text-sm text-[#69736B]">Branch {{ $membership->branch?->name ?? '' }}. Data is limited to your active branch assignment.</p></div>
    @include('admin.sales-reports.partials.filters', ['routeName' => 'admin.sales-reports.branch'])
    @include('admin.sales-reports.partials.summary')
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @include('admin.sales-reports.partials.channel-breakdown')
        @include('admin.sales-reports.partials.employee-breakdown')
        @include('admin.sales-reports.partials.attribution-breakdown')
    </div>
    @include('admin.sales-reports.partials.orders', ['showEmployee' => true, 'showBranch' => false, 'detailRoute' => 'admin.orders.show'])
</div>
@endsection
