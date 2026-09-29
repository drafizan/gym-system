@extends('layouts.app')

@section('title', 'PT Sessions')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>PT Sessions</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>Session Tracking</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-light" href="{{ route('pt.member-packages.create') }}">Assign Package</a>
            <a class="btn btn-primary" href="{{ route('pt.sessions.create') }}">Record Session</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header"><h2>Active PT Balances</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Package</th>
                        <th>Used</th>
                        <th>Remaining</th>
                        <th>Purchased</th>
                        <th>Expiry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($balances as $balance)
                        <tr>
                            <td><span class="table-primary-text">{{ $balance->member?->full_name }}</span><span class="table-muted">{{ $balance->member?->member_no }}</span></td>
                            <td>{{ $balance->package?->name }}</td>
                            <td>{{ $balance->used_sessions }} / {{ $balance->total_sessions }}</td>
                            <td><span class="sale-type-pill {{ $balance->remainingSessions() > 0 ? 'success' : 'muted-pill' }}">{{ $balance->remainingSessions() }}</span></td>
                            <td>{{ $balance->purchased_at?->format('d M Y') }}</td>
                            <td>{{ $balance->expires_at?->format('d M Y') ?? '-' }}</td>
                            <td><span class="sale-type-pill {{ $balance->isActive() ? 'success' : 'muted-pill' }}">{{ str($balance->status)->headline() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No active PT package balances found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-shell">
        <div class="table-shell-header"><h2>Session Log</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Trainer</th>
                        <th>Package</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Commission</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>{{ $session->session_date?->format('d M Y') }}</td>
                            <td>{{ $session->member?->full_name }}</td>
                            <td>{{ $session->trainer?->name }}</td>
                            <td>{{ $session->memberPackage?->package?->name }}</td>
                            <td>{{ $session->duration_minutes }} min</td>
                            <td><span class="sale-type-pill {{ $session->status === 'completed' ? 'success' : 'muted-pill' }}">{{ str($session->status)->headline() }}</span></td>
                            <td>RM {{ number_format((float) $session->commission_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No PT sessions recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">{{ $sessions->links() }}</div>
    </section>
@endsection
