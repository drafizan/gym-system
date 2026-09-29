@extends('layouts.app')

@section('title', 'Receipt '.$sale->receipt_no)

@section('breadcrumbs')
    <a href="{{ route('sales.history') }}">Sales History</a>
    <span>{{ $sale->receipt_no }}</span>
@endsection

@php
    $systemSettings = app(\App\Support\SystemSettings::class);
    $receiptFooter = trim((string) $systemSettings->get('receipt_footer'));
@endphp

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Receipt</p>
            <h1>{{ $sale->receipt_no }}</h1>
        </div>
        <div class="toolbar-actions">
            <button class="btn btn-light" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <section class="form-card receipt-card">
        <div class="receipt-header">
            <div>
                <strong>{{ $systemSettings->get('gym_name') }}</strong>
                @if ($systemSettings->get('gym_address'))
                    <span>{{ $systemSettings->get('gym_address') }}</span>
                @endif
                <span>{{ $systemSettings->get('gym_contact_number') }}</span>
                <span>{{ $sale->completed_at?->format('d M Y, h:i A') }}</span>
            </div>
            <div>
                <strong>RM {{ number_format((float) $sale->total, 2) }}</strong>
                <span>{{ str($sale->status)->headline() }}</span>
            </div>
        </div>

        <div class="profile-info-table profile-info-table-wide">
            <div><span>Member</span><strong>{{ $sale->member?->full_name ?? '-' }}</strong></div>
            <div><span>Cashier</span><strong>{{ $sale->cashier?->name ?? '-' }}</strong></div>
            <div><span>Sale Type</span><strong>{{ \App\Enums\SaleType::tryFrom($sale->sale_type)?->label() ?? str($sale->sale_type)->headline() }}</strong></div>
            <div><span>Payment</span><strong>{{ $sale->payments->pluck('payment_method')->map(fn ($method) => \App\Enums\PaymentMethod::labelFor($method))->join(', ') }}</strong></div>
        </div>

        <div class="table-responsive">
            <table>
                <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>RM {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>RM {{ number_format((float) $item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($receiptFooter !== '')
            <p class="receipt-footer-note">{{ $receiptFooter }}</p>
        @endif
    </section>
@endsection
