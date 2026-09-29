@extends('layouts.app')

@section('title', 'Edit Product')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('products.index') }}">Products</a>
    <span>Edit Product</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>Edit Product</h1>
        </div>
    </div>

    <x-panel title="Product Details">
        <form method="POST" action="{{ route('products.update', $product) }}">
            @csrf
            @method('PUT')
            @include('products.partials.form')
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('products.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </x-panel>
@endsection
