@extends('layouts.app')

@section('title', 'Trainers')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Trainers</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>Trainer Management</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('pt.trainers.create') }}">New Trainer</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header"><h2>Trainer List</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Specialization</th>
                        <th>Commission</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trainers as $trainer)
                        <tr>
                            <td><span class="table-primary-text">{{ $trainer->name }}</span><span class="table-muted">{{ $trainer->email ?? '-' }}</span></td>
                            <td>{{ $trainer->phone ?? '-' }}</td>
                            <td>{{ $trainer->specialization ?? '-' }}</td>
                            <td>RM {{ number_format((float) $trainer->commission_per_session, 2) }} / session</td>
                            <td><span class="sale-type-pill {{ $trainer->status === 'active' ? 'success' : 'muted-pill' }}">{{ str($trainer->status)->headline() }}</span></td>
                            <td><x-icon-action icon="edit" label="Edit trainer" href="{{ route('pt.trainers.edit', $trainer) }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No trainers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">{{ $trainers->links() }}</div>
    </section>
@endsection
