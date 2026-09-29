@extends('layouts.app')

@section('title', 'Products')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Products</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>Products</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('products.create') }}">New Product</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>Product List</h2>
            <form class="toolbar-actions" method="GET" action="{{ route('products.index') }}">
                <input class="table-search" type="search" name="search" value="{{ $search }}" placeholder="Search SKU, name, description">
                <select class="table-filter" name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select class="table-filter" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
                <button class="btn btn-light icon-action" type="submit" data-tooltip="Search products" aria-label="Search products">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Reorder</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->sku }}</td>
                            <td>
                                <span class="table-primary-text">{{ $product->name }}</span>
                                @if ($product->description)
                                    <span class="table-muted">{{ $product->description }}</span>
                                @endif
                            </td>
                            <td>{{ $product->category?->name ?? '-' }}</td>
                            <td>RM {{ number_format((float) $product->selling_price, 2) }}</td>
                            <td>
                                <span class="sale-type-pill {{ $product->isLowStock() ? 'danger' : 'success' }}">{{ $product->stock_quantity }}</span>
                            </td>
                            <td>{{ $product->reorder_level }}</td>
                            <td><span class="sale-type-pill {{ $product->status === 'active' ? 'success' : 'muted-pill' }}">{{ str($product->status)->headline() }}</span></td>
                            <td><x-icon-action icon="edit" label="Edit product" href="{{ route('products.edit', $product) }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">{{ $products->links() }}</div>
    </section>
@endsection
