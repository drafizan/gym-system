@extends('layouts.app')

@section('title', 'Members')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>{{ $pageTitle ?? 'Members' }}</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Gym Operations</p>
            <h1>{{ $pageTitle ?? 'Members' }}</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('members.create') }}">Register Member</a>
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>{{ $listTitle ?? 'Member List' }}</h2>
            <form class="table-actions" method="GET" action="{{ url()->current() }}">
                <input class="table-search" type="search" name="search" value="{{ $search }}" placeholder="Search name, phone, member no.">
                <select class="table-filter" name="filter" aria-label="Filter members">
                    <option value="">All members</option>
                    <option value="active" @selected(($filter ?? '') === 'active')>Active</option>
                    <option value="expiring" @selected(($filter ?? '') === 'expiring')>Expiring</option>
                    <option value="expired" @selected(($filter ?? '') === 'expired')>Expired</option>
                    <option value="suspended" @selected(($filter ?? '') === 'suspended')>Suspended</option>
                    <option value="missing_photo" @selected(($filter ?? '') === 'missing_photo')>Missing Photo</option>
                    <option value="has_rfid" @selected(($filter ?? '') === 'has_rfid')>Has RFID</option>
                    <option value="no_rfid" @selected(($filter ?? '') === 'no_rfid')>No RFID</option>
                </select>
                <x-icon-action icon="search" label="Search members" type="submit" />
                <x-icon-action icon="download" label="Export members" href="{{ route('members.export', request()->only(['search', 'filter'])) }}" />
                <x-icon-action icon="upload" label="Import members CSV" type="button" data-modal-open="member-import" />
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Member No.</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td>{{ $member->member_no }}</td>
                            <td>{{ $member->full_name }}</td>
                            <td>{{ $member->phone }}</td>
                            <td><span class="sale-type-pill {{ $member->displayStatusTone() }}">{{ str($member->displayStatus())->headline() }}</span></td>
                            <td>{{ $member->updated_at->format('d M Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <x-icon-action icon="eye" label="View profile" href="{{ route('members.show', $member) }}" />
                                    <x-icon-action icon="edit" label="Edit member" href="{{ route('members.edit', $member) }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">
            {{ $members->links() }}
        </div>
    </section>

    <div class="modal-backdrop" data-modal="member-import" aria-hidden="true">
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="member-import-title">
            <button type="button" class="modal-close" data-modal-close aria-label="Close import modal"></button>
            <p class="eyebrow">Members</p>
            <h2 id="member-import-title">Import Members CSV</h2>
            <p>Select a CSV file from this computer. The file must include `full_name` and `phone` columns.</p>

            <form class="modal-form" method="POST" action="{{ route('members.import') }}" enctype="multipart/form-data">
                @csrf
                <label class="field">
                    <span>CSV File</span>
                    <input type="file" name="members_csv" accept=".csv,text/csv" required>
                </label>

                <div class="modal-actions">
                    <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                    <button class="btn btn-primary" type="submit">Import Members</button>
                </div>
            </form>
        </section>
    </div>
@endsection
