@extends('layouts.app')

@section('title', 'New Package')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('membership-packages.index') }}">Membership Packages</a>
    <span>New Package</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Memberships</p>
            <h1>New Package</h1>
        </div>
    </div>

    <x-panel title="Package Details">
        <form method="POST" action="{{ route('membership-packages.store') }}">
            @csrf
            @include('memberships.packages.partials.form')
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('membership-packages.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Save Package</button>
            </div>
        </form>
    </x-panel>
@endsection
