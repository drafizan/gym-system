@extends('layouts.app')

@section('title', 'Edit Package')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('membership-packages.index') }}">Membership Packages</a>
    <span>Edit Package</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Memberships</p>
            <h1>Edit Package</h1>
        </div>
    </div>

    <x-panel title="Package Details">
        <form id="package-update-form" method="POST" action="{{ route('membership-packages.update', $membershipPackage) }}">
            @csrf
            @method('PUT')
            @include('memberships.packages.partials.form')
        </form>
        <div class="form-actions split-actions">
            <form method="POST" action="{{ route('membership-packages.destroy', $membershipPackage) }}" onsubmit="return confirm('Delete this package? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger-soft" type="submit">Delete Package</button>
            </form>
            <div class="toolbar-actions">
                <a class="btn btn-light" href="{{ route('membership-packages.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit" form="package-update-form">Save Changes</button>
            </div>
        </div>
    </x-panel>
@endsection
