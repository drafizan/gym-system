@extends('layouts.app')

@section('title', 'Membership Packages')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Membership Packages</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Memberships</p>
            <h1>Membership Packages</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('membership-packages.create') }}">New Package</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>Package List</h2>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Access</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packages as $package)
                        <tr>
                            <td>{{ $package->name }}</td>
                            <td>{{ $package->duration_days }} days</td>
                            <td>RM {{ number_format((float) $package->price, 2) }}</td>
                            <td>{{ $package->access_allowed ? 'Allowed' : 'Not allowed' }}</td>
                            <td><span class="sale-type-pill {{ $package->status === 'active' ? 'success' : 'muted-pill' }}">{{ str($package->status)->headline() }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <x-icon-action icon="edit" label="Edit package" href="{{ route('membership-packages.edit', $package) }}" />
                                    <form method="POST" action="{{ route('membership-packages.destroy', $package) }}" onsubmit="return confirm('Delete this package? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-action icon="trash" label="Delete package" type="submit" variant="danger-soft" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No packages found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">
            {{ $packages->links() }}
        </div>
    </section>
@endsection
