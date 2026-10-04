@extends('layouts.app')

@section('title', 'Password Reset')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('users.index') }}">Users</a>
    <span>Password Reset</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div><p class="eyebrow">Administration</p><h1>Password Reset</h1>
            <p>Select a user, enter a new password and confirmation, then save changes.</p>
        </div>
    </div>
    <section class="table-shell">
        <div class="table-shell-header"><h2>Select User</h2></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($users as $managedUser)
                        <tr>
                            <td>{{ $managedUser->name }}</td><td>{{ $managedUser->username }}</td>
                            <td>{{ $managedUser->role?->label ?? '-' }}</td>
                            <td><a class="btn btn-light" href="{{ route('users.edit', $managedUser) }}#password">Reset Password</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
