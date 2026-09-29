@extends('layouts.app')

@section('title', 'Edit Category')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('product-categories.index') }}">Product Categories</a>
    <span>Edit Category</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>Edit Category</h1>
        </div>
    </div>

    <x-panel title="Category Details">
        <form id="category-update-form" method="POST" action="{{ route('product-categories.update', $productCategory) }}">
            @csrf
            @method('PUT')
            @include('products.categories.partials.form')
        </form>
        <div class="form-actions split-actions">
            <form method="POST" action="{{ route('product-categories.destroy', $productCategory) }}" onsubmit="return confirm('Delete this category? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger-soft" type="submit">Delete Category</button>
            </form>
            <div class="toolbar-actions">
                <a class="btn btn-light" href="{{ route('product-categories.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit" form="category-update-form">Save Changes</button>
            </div>
        </div>
    </x-panel>
@endsection
