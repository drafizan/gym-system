@extends('layouts.app')

@section('title', 'Users')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Users</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Users & Roles</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('users.create') }}">New User</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>System Users</h2>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $managedUser)
                        <tr>
                            <td>{{ $managedUser->name }}</td>
                            <td>{{ $managedUser->username }}</td>
                            <td>{{ $managedUser->role?->label ?? '-' }}</td>
                            <td>
                                <span class="sale-type-pill {{ $managedUser->is_active ? 'success' : 'muted-pill' }}">
                                    {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $managedUser->last_login_at?->format('d M Y, h:i A') ?? '-' }}</td>
                            <td>
                                <div class="table-actions">
                                    <x-icon-action icon="edit" label="Edit user" href="{{ route('users.edit', $managedUser) }}" />
                                    @if ($managedUser->is_active)
                                        <form method="POST" action="{{ route('users.deactivate', $managedUser) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-action icon="ban" label="Deactivate user" type="submit" />
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('users.reactivate', $managedUser) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-action icon="restore" label="Reactivate user" type="submit" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <section class="table-shell" id="configured-roles" style="margin-top: 24px;">
        <div class="table-shell-header">
            <h2>Configured Roles</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Name</th><th>Permissions</th><th>Users</th></tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>{{ $role->label }}<br><small>{{ $role->name }}</small></td>
                            <td style="white-space: normal; min-width: 260px;">{{ $role->permissions->pluck('label')->join(', ') ?: 'None' }}</td>
                            <td>{{ $role->users_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No roles configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
