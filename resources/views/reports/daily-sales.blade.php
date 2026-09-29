@extends('layouts.app')

@section('title', 'Daily Sales Report')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Reports</a>
    <span>Daily Sales Report</span>
@endsection

@section('content')
    @php
        $summary = $report['summary'];
        $payments = $report['payment_breakdown'];
        $transactions = $report['transactions'];
    @endphp

    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Reports</p>
            <h1>Daily Sales Report</h1>
        </div>
    </div>

    <form class="report-filter-panel" method="GET" action="{{ route('reports.daily-sales') }}">
        <div class="report-field">
            <label>Date From</label>
            <input type="date" name="date_from" value="{{ $report['filters']['date_from'] }}">
        </div>
        <div class="report-field">
            <label>Date To</label>
            <input type="date" name="date_to" value="{{ $report['filters']['date_to'] }}">
        </div>
        <div class="report-field">
            <label>Payment</label>
            <select name="payment_method">
                <option value="">All payments</option>
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method }}" @selected($report['filters']['payment_method'] === $method)>{{ \App\Enums\PaymentMethod::labelFor($method) }}</option>
                @endforeach
            </select>
        </div>
        <div class="report-filter-actions">
            <button class="btn btn-primary" type="submit">Generate Report</button>
            <a class="btn btn-light" href="{{ route('reports.daily-sales.export', request()->query()) }}">
                <span class="button-icon download"></span>
                Export XLSX
            </a>
        </div>
    </form>

    @include('reports.partials.sales-summary', ['summary' => $summary, 'payments' => $payments])

    <section class="sales-details-card">
        <div class="sales-details-header"><h2>Sales Details</h2></div>
        @include('reports.partials.sales-table', ['transactions' => $transactions])
    </section>
@endsection
