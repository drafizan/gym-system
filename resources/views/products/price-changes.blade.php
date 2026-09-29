@extends('layouts.app')

@section('title', 'Price Changes')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('products.index') }}">Products</a>
    <span>Price Changes</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>Price Changes</h1>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header"><h2>Price History</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Old Price</th>
                        <th>New Price</th>
                        <th>Changed By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($priceChanges as $change)
                        <tr>
                            <td>{{ $change->changed_at?->format('d M Y, h:i A') }}</td>
                            <td>{{ $change->product?->name ?? '-' }}</td>
                            <td>RM {{ number_format((float) $change->old_price, 2) }}</td>
                            <td>RM {{ number_format((float) $change->new_price, 2) }}</td>
                            <td>{{ $change->changedBy?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No price changes recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">{{ $priceChanges->links() }}</div>
    </section>
@endsection
