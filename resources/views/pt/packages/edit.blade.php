@extends('layouts.app')

@section('title', 'Edit PT Package')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('pt.packages.index') }}">PT Packages</a>
    <span>Edit PT Package</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div><p class="eyebrow">Personal Training</p><h1>Edit PT Package</h1></div>
    </div>

    <x-panel title="PT Package Details">
        <form method="POST" action="{{ route('pt.packages.update', $package) }}">
            @csrf
            @method('PUT')
            @include('pt.packages.partials.form')
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('pt.packages.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </x-panel>
@endsection
