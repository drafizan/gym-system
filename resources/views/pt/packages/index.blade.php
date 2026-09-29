@extends('layouts.app')

@section('title', 'PT Packages')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>PT Packages</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>PT Package Management</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('pt.packages.create') }}">New PT Package</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header"><h2>PT Package List</h2></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Sessions</th>
                        <th>Price</th>
                        <th>Commission</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packages as $package)
                        <tr>
                            <td><span class="table-primary-text">{{ $package->name }}</span></td>
                            <td>{{ $package->sessions_count }}</td>
                            <td>RM {{ number_format((float) $package->price, 2) }}</td>
                            <td>RM {{ number_format((float) $package->commission_per_session, 2) }} / session</td>
                            <td>{{ $package->validity_days ? $package->validity_days.' days' : 'No expiry' }}</td>
                            <td><span class="sale-type-pill {{ $package->status === 'active' ? 'success' : 'muted-pill' }}">{{ str($package->status)->headline() }}</span></td>
                            <td><x-icon-action icon="edit" label="Edit PT package" href="{{ route('pt.packages.edit', $package) }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No PT packages found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">{{ $packages->links() }}</div>
    </section>
@endsection
