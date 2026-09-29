@extends('layouts.app')

@section('title', 'Audit Trail')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Audit Trail</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Security</p>
            <h1>Audit Trail</h1>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>Activity Logs</h2>
        </div>

        <form class="audit-filters" method="GET" action="{{ route('audit.index') }}">
            <label class="field">
                <span>Module</span>
                <select name="module">
                    <option value="">All modules</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') === $module)>{{ str($module)->headline() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Action</span>
                <select name="action">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ str($action)->headline() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>User</span>
                <select name="user_id">
                    <option value="">All users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>
                            {{ $user->name }} ({{ $user->username }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>From</span>
                <input type="date" name="date_from" value="{{ request('date_from') }}">
            </label>

            <label class="field">
                <span>To</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}">
            </label>

            <div class="audit-filter-actions">
                <x-icon-action icon="restore" label="Reset filters" href="{{ route('audit.index') }}" />
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>IP</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td>{{ str($log->module)->headline() }}</td>
                            <td><span class="sale-type-pill muted-pill">{{ str($log->action)->headline() }}</span></td>
                            <td>{{ class_basename($log->record_type) ?: '-' }} {{ $log->record_id ? '#'.$log->record_id : '' }}</td>
                            <td>{{ $log->ip_address ?? '-' }}</td>
                            <td>
                                <x-icon-action icon="eye" label="View details" href="{{ route('audit.show', $log) }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No audit logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">
            {{ $logs->links() }}
        </div>
    </section>
@endsection
