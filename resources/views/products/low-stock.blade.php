@extends('layouts.app')

@section('title', 'Low Stock')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('products.index') }}">Products</a>
    <span>Low Stock</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>Low Stock</h1>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header"><h2>Products At Or Below Reorder Level</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->category?->name ?? '-' }}</td>
                            <td><span class="sale-type-pill danger">{{ $product->stock_quantity }}</span></td>
                            <td>{{ $product->reorder_level }}</td>
                            <td><span class="sale-type-pill {{ $product->status === 'active' ? 'success' : 'muted-pill' }}">{{ str($product->status)->headline() }}</span></td>
                            <td><x-icon-action icon="edit" label="Edit product" href="{{ route('products.edit', $product) }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No low stock products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">{{ $products->links() }}</div>
    </section>
@endsection
