@extends('layouts.app')

@section('title', 'Change Password')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Change Password</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Security</p>
            <h1>Change Password</h1>
        </div>
    </div>

    <x-panel title="Password Details">
        <form class="form-grid" method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')

            <label class="field">
                <span>Current Password</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>

            <label class="field">
                <span>New Password</span>
                <input type="password" name="password" autocomplete="new-password" required>
            </label>

            <label class="field">
                <span>Confirm New Password</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Update Password</button>
            </div>
        </form>
    </x-panel>
@endsection
