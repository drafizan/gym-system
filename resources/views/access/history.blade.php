@extends('layouts.app')

@section('title', 'Door Event History')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Door Event History</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Access Control</p>
            <h1>Door Event History</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-light" href="{{ route('settings.index') }}">Door Configuration</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>Dahua Device Events</h2>
            <form class="toolbar-actions" method="GET" action="{{ route('door-access.history') }}">
                <select class="table-filter" name="door_id" aria-label="Filter door">
                    <option value="">All doors</option>
                    @foreach ($doors as $door)
                        <option value="{{ $door->id }}" @selected((string) ($filters['door_id'] ?? '') === (string) $door->id)>
                            {{ $door->displayName() }}
                        </option>
                    @endforeach
                </select>
                <input class="table-search" type="search" name="card_number" value="{{ $filters['card_number'] }}" placeholder="Search card number">
                <input class="table-filter" type="date" name="date_from" value="{{ $filters['date_from'] }}" aria-label="Date from">
                <input class="table-filter" type="date" name="date_to" value="{{ $filters['date_to'] }}" aria-label="Date to">
                <select class="table-filter compact-filter" name="limit" aria-label="Limit results">
                    @foreach ([25, 50, 100, 200] as $limit)
                        <option value="{{ $limit }}" @selected((int) $filters['limit'] === $limit)>{{ $limit }}</option>
                    @endforeach
                </select>
                <x-icon-action icon="search" label="Search device events" type="submit" />
            </form>
        </div>

        @if ($historyErrors->isNotEmpty())
            <div class="alert alert-warning">
                @foreach ($historyErrors as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Door</th>
                        <th>Card Number</th>
                        <th>User ID</th>
                        <th>Result</th>
                        <th>Reason</th>
                        <th>Method</th>
                        <th>Reader</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ filled($event['time'] ?? null) ? \Illuminate\Support\Carbon::parse($event['time'])->format('d M Y, H:i:s') : '-' }}</td>
                            <td>{{ $event['door_name'] ?? '-' }}</td>
                            <td>
                                {{ $event['card_number_decimal'] ?: ($event['card_number'] ?: '-') }}
                                @if (! empty($event['card_number_decimal']) && ! empty($event['card_number']))
                                    <span class="table-muted">Device: {{ $event['card_number'] }}</span>
                                @endif
                            </td>
                            <td>{{ $event['user_id'] ?: '-' }}</td>
                            <td>
                                <span class="sale-type-pill {{ ($event['success'] ?? false) ? 'success' : 'danger' }}">
                                    {{ ($event['success'] ?? false) ? 'Allowed' : 'Rejected' }}
                                </span>
                            </td>
                            <td>{{ $event['error_label'] ?? '-' }}</td>
                            <td>{{ $event['method_label'] ?? ('Method '.($event['method'] ?? '-')) }}</td>
                            <td>{{ $event['reader_id'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No device events found for this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">
            <span>Showing {{ $events->count() }} device event(s)</span>
        </div>
    </section>
@endsection
