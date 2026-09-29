@extends('layouts.app')

@section('title', 'New Product')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('products.index') }}">Products</a>
    <span>New Product</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>New Product</h1>
        </div>
    </div>

    <x-panel title="Product Details">
        <form method="POST" action="{{ route('products.store') }}">
            @csrf
            @include('products.partials.form')
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('products.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Save Product</button>
            </div>
        </form>
    </x-panel>
@endsection
