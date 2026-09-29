@extends('layouts.app')

@section('title', $title)

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>{{ $title }}</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Memberships</p>
            <h1>{{ $title }}</h1>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>{{ $title }} List</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Package</th>
                        <th>Start</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($memberships as $membership)
                        <tr>
                            <td>{{ $membership->member?->full_name }}<br><span class="muted">{{ $membership->member?->member_no }}</span></td>
                            <td>{{ $membership->package?->name }}</td>
                            <td>{{ $membership->start_date->format('d M Y') }}</td>
                            <td>{{ $membership->end_date->format('d M Y') }}</td>
                            <td><span class="sale-type-pill {{ $membership->status === 'active' ? 'success' : 'muted-pill' }}">{{ str($membership->status)->headline() }}</span></td>
                            <td>
                                <x-icon-action icon="eye" label="View profile" href="{{ route('members.show', $membership->member) }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No memberships found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            {{ $memberships->links() }}
        </div>
    </section>
@endsection
