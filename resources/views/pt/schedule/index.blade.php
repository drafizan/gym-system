@extends('layouts.app')

@section('title', 'PT Schedule')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>PT Schedule</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>PT Schedule</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('pt.schedule.create') }}">Schedule Session</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</h2>
            <form class="toolbar-actions" method="GET" action="{{ route('pt.schedule.index') }}">
                <input class="table-filter" type="date" name="date" value="{{ $date }}">
                <select class="table-filter" name="trainer_id">
                    <option value="">All trainers</option>
                    @foreach ($trainers as $trainer)
                        <option value="{{ $trainer->id }}" @selected((string) $trainerId === (string) $trainer->id)>{{ $trainer->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary" type="submit">Filter</button>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Member</th>
                        <th>Trainer</th>
                        <th>Package</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>
                                <span class="table-primary-text">
                                    {{ $session->scheduled_start_at?->format('H:i') ?? '-' }}
                                    @if ($session->scheduled_end_at)
                                        - {{ $session->scheduled_end_at->format('H:i') }}
                                    @endif
                                </span>
                                <span class="table-muted">{{ $session->session_date?->format('D') }}</span>
                            </td>
                            <td><span class="table-primary-text">{{ $session->member?->full_name }}</span><span class="table-muted">{{ $session->member?->member_no }}</span></td>
                            <td>{{ $session->trainer?->name }}</td>
                            <td>{{ $session->memberPackage?->package?->name }}</td>
                            <td>{{ $session->duration_minutes }} min</td>
                            <td>
                                <span class="sale-type-pill {{ $session->status === 'completed' ? 'success' : ($session->status === 'scheduled' ? 'warning' : 'muted-pill') }}">
                                    {{ str($session->status)->headline() }}
                                </span>
                            </td>
                            <td>
                                @if ($session->status === 'scheduled')
                                    <div class="table-actions">
                                        <form method="POST" action="{{ route('pt.schedule.complete', $session) }}" onsubmit="return confirm('Mark this PT session as completed?');">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-action icon="check" label="Complete session" type="submit" variant="light" />
                                        </form>
                                        <form method="POST" action="{{ route('pt.schedule.cancel', $session) }}" onsubmit="return confirm('Cancel this PT session?');">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-action icon="ban" label="Cancel session" type="submit" variant="danger-soft" />
                                        </form>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No PT sessions scheduled for this date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
