@extends('layouts.app')

@section('title', $title)

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('users.index') }}">Users</a>
    <span>{{ $title }}</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div><p class="eyebrow">Administration</p><h1>{{ $title }}</h1></div>
    </div>
    <section class="table-shell">
        <div class="table-shell-header"><h2>Configured {{ $title }}</h2></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Name</th><th>{{ $title === 'Roles' ? 'Permissions' : 'Assigned Roles' }}</th>@if ($title === 'Roles')<th>Users</th>@endif</tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->label }}<br><small>{{ $record->name }}</small></td>
                            <td>{{ ($title === 'Roles' ? $record->permissions : $record->roles)->pluck('label')->join(', ') ?: 'None' }}</td>
                            @if ($title === 'Roles')<td>{{ $record->users_count }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $title === 'Roles' ? 3 : 2 }}">No {{ strtolower($title) }} configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
