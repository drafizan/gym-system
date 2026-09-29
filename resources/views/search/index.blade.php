@extends('layouts.app')

@section('title', 'Search')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Search</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Workspace</p>
            <h1>Search Results</h1>
        </div>
    </div>

    <section class="table-shell search-results-panel">
        <div class="table-shell-header">
            <h2>{{ $query !== '' ? 'Results for "'.$query.'"' : 'Search members, cards, receipts' }}</h2>
            <form class="table-actions" method="GET" action="{{ route('search.index') }}">
                <input class="table-search" type="search" name="q" value="{{ $query }}" placeholder="Search members, cards, receipts">
                <x-icon-action icon="search" label="Search" type="submit" />
            </form>
        </div>

        @if ($query === '')
            <div class="search-empty-state">Enter a member name, member number, RFID card number, phone number, or receipt number.</div>
        @else
            <div class="search-results-grid">
                <section>
                    <div class="search-section-title">
                        <h3>Members & Cards</h3>
                        <span>{{ $members->count() }} found</span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member No.</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>RFID Card</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($members as $member)
                                    <tr>
                                        <td>{{ $member->member_no }}</td>
                                        <td><span class="table-primary-text">{{ $member->full_name }}</span></td>
                                        <td>{{ $member->phone }}</td>
                                        <td>{{ $member->activeRfidCard?->card_number ?? $member->rfid_card_number ?? '-' }}</td>
                                        <td><x-icon-action icon="eye" label="View profile" href="{{ route('members.show', $member) }}" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">No matching members or cards found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <div class="search-section-title">
                        <h3>Receipts</h3>
                        <span>{{ $sales->count() }} found</span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Receipt</th>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sales as $sale)
                                    <tr>
                                        <td>{{ $sale->receipt_no }}</td>
                                        <td>{{ $sale->completed_at?->format('d M Y, h:i A') }}</td>
                                        <td>{{ $sale->member?->full_name ?? '-' }}</td>
                                        <td>RM {{ number_format((float) $sale->total, 2) }}</td>
                                        <td><x-icon-action icon="eye" label="View receipt" href="{{ route('sales.receipt', $sale) }}" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">No matching receipts found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endif
    </section>
@endsection
