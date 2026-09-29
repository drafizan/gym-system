@extends('layouts.app')

@section('title', 'Create User')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('users.index') }}">Users</a>
    <span>Create</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Create User</h1>
        </div>
    </div>

    <x-panel title="User Details">
        <form class="form-grid" method="POST" action="{{ route('users.store') }}">
            @csrf

            @include('users.partials.form', ['managedUser' => null])

            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('users.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Create User</button>
            </div>
        </form>
    </x-panel>
@endsection
