@extends('layouts.app')

@section('title', 'Product Categories')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('products.index') }}">Products</a>
    <span>Categories</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>Product Categories</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('product-categories.create') }}">New Category</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header"><h2>Category List</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->description ?? '-' }}</td>
                            <td>{{ $category->products_count }}</td>
                            <td><span class="sale-type-pill {{ $category->status === 'active' ? 'success' : 'muted-pill' }}">{{ str($category->status)->headline() }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <x-icon-action icon="edit" label="Edit category" href="{{ route('product-categories.edit', $category) }}" />
                                    <form method="POST" action="{{ route('product-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-action icon="trash" label="Delete category" type="submit" variant="danger-soft" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No categories found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">{{ $categories->links() }}</div>
    </section>
@endsection
