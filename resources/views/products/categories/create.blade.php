@extends('layouts.app')

@section('title', 'New Category')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('product-categories.index') }}">Product Categories</a>
    <span>New Category</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Product & Pricing</p>
            <h1>New Category</h1>
        </div>
    </div>

    <x-panel title="Category Details">
        <form method="POST" action="{{ route('product-categories.store') }}">
            @csrf
            @include('products.categories.partials.form')
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('product-categories.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Save Category</button>
            </div>
        </form>
    </x-panel>
@endsection
