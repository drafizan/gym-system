@extends('layouts.app')

@section('title', 'Edit User')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('users.index') }}">Users</a>
    <span>Edit</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Edit User</h1>
        </div>
    </div>

    <x-panel title="User Details">
        <form class="form-grid" method="POST" action="{{ route('users.update', $managedUser) }}">
            @csrf
            @method('PUT')

            @include('users.partials.form', ['managedUser' => $managedUser])

            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('users.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </x-panel>
@endsection
