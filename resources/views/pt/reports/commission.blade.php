@extends('layouts.app')

@section('title', 'Trainer Commission Report')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Trainer Commission Report</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>Trainer Commission Report</h1>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>Report Filters</h2>
            <form class="toolbar-actions" method="GET" action="{{ route('pt.reports.commission') }}">
                <input class="table-filter" type="date" name="date_from" value="{{ $dateFrom }}">
                <input class="table-filter" type="date" name="date_to" value="{{ $dateTo }}">
                <select class="table-filter" name="trainer_id">
                    <option value="">All trainers</option>
                    @foreach ($trainers as $trainer)
                        <option value="{{ $trainer->id }}" @selected((string) $trainerId === (string) $trainer->id)>{{ $trainer->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary" type="submit">Generate Report</button>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Trainer</th>
                        <th>Completed Sessions</th>
                        <th>Commission Payable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summary as $row)
                        <tr>
                            <td>{{ $row['trainer']?->name ?? '-' }}</td>
                            <td>{{ $row['sessions'] }}</td>
                            <td>RM {{ number_format((float) $row['commission'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No completed PT sessions found for this filter.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th>{{ $summary->sum('sessions') }}</th>
                        <th>RM {{ number_format((float) $summary->sum('commission'), 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="table-shell">
        <div class="table-shell-header"><h2>Session Details</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Trainer</th>
                        <th>Member</th>
                        <th>Duration</th>
                        <th>Commission</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>{{ $session->session_date?->format('d M Y') }}</td>
                            <td>{{ $session->trainer?->name }}</td>
                            <td>{{ $session->member?->full_name }}</td>
                            <td>{{ $session->duration_minutes }} min</td>
                            <td>RM {{ number_format((float) $session->commission_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No session details available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
