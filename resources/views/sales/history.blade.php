@extends('layouts.app')

@section('title', 'Sales History')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Sales History</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Sales</p>
            <h1>Sales History</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('sales.pos') }}">New Sale</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>Transactions</h2>
            <form class="table-actions" method="GET" action="{{ route('sales.history') }}">
                <input class="table-search" type="search" name="search" value="{{ $search }}" placeholder="Search receipt or member">
                <x-icon-action icon="search" label="Search sales" type="submit" />
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Type</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->receipt_no }}</td>
                            <td>{{ $sale->completed_at?->format('d M Y, h:i A') }}</td>
                            <td>{{ $sale->member?->full_name ?? '-' }}</td>
                            <td>{{ \App\Enums\SaleType::tryFrom($sale->sale_type)?->label() ?? str($sale->sale_type)->headline() }}</td>
                            <td>{{ $sale->payments->pluck('payment_method')->map(fn ($method) => \App\Enums\PaymentMethod::labelFor($method))->join(', ') }}</td>
                            <td>RM {{ number_format((float) $sale->total, 2) }}</td>
                            <td><span class="sale-type-pill {{ $sale->status === 'completed' ? 'success' : 'danger' }}">{{ str($sale->status)->headline() }}</span></td>
                            <td><x-icon-action icon="eye" label="View receipt" href="{{ route('sales.receipt', $sale) }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No sales found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">{{ $sales->links() }}</div>
    </section>
@endsection
