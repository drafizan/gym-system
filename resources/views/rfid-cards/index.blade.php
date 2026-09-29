@extends('layouts.app')

@section('title', $pageTitle)

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    @if ($member)
        <a href="{{ route('members.show', $member) }}">{{ $member->member_no }}</a>
    @endif
    <span>{{ $pageTitle }}</span>
@endsection

@php
    $statusTone = fn (string $value): string => match ($value) {
        'active' => 'success',
        'blocked' => 'danger',
        default => 'muted-pill',
    };
@endphp

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Access Control</p>
            <h1>{{ $member ? $member->member_no.' RFID Cards' : $pageTitle }}</h1>
        </div>
        <div class="toolbar-actions">
            @if ($member)
                <x-icon-action icon="back" label="Back to member profile" href="{{ route('members.show', $member) }}" />
                @unless ($member->activeRfidCard)
                    <a class="btn btn-primary" href="{{ route('members.rfid-cards.create', $member) }}">Assign Card</a>
                @endunless
            @endif
        </div>
    </div>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>{{ $member ? 'Member Card History' : 'RFID Card List' }}</h2>
            <form class="toolbar-actions" method="GET" action="{{ url()->current() }}">
                <input class="table-search" type="search" name="search" value="{{ $search }}" placeholder="Search card, member, phone">
                <select class="table-filter" name="status" aria-label="Filter RFID card status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $case)
                        <option value="{{ $case->value }}" @selected($status === $case->value)>{{ str($case->value)->headline() }}</option>
                    @endforeach
                </select>
                <x-icon-action icon="search" label="Search RFID cards" type="submit" />
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Card Number</th>
                        @unless ($member)
                            <th>Member</th>
                        @endunless
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Deactivated</th>
                        <th>Remarks</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cards as $card)
                        <tr>
                            <td>{{ $card->card_number }}</td>
                            @unless ($member)
                                <td>
                                    @if ($card->member)
                                        <span class="table-primary-text">{{ $card->member->full_name }}</span>
                                        <span class="table-muted">{{ $card->member->member_no }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endunless
                            <td><span class="sale-type-pill {{ $statusTone($card->status) }}">{{ str($card->status)->headline() }}</span></td>
                            <td>
                                <span>{{ $card->assigned_at?->format('d M Y') ?? '-' }}</span>
                                @if ($card->assignedBy)
                                    <span class="table-muted">{{ $card->assignedBy->name }}</span>
                                @endif
                            </td>
                            <td>{{ $card->deactivated_at?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $card->remarks ?: '-' }}</td>
                            <td>
                                <div class="table-actions">
                                    @if ($card->member)
                                        <x-icon-action icon="eye" label="View member profile" href="{{ route('members.show', $card->member) }}" />
                                    @endif
                                    @if ($card->isActive())
                                        <x-icon-action icon="edit" label="Update card number" href="{{ route('rfid-cards.replace', $card) }}" />
                                        <form method="POST" action="{{ route('rfid-cards.deactivate', $card) }}" onsubmit="return confirm('Deactivate this RFID card?');">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-action icon="ban" label="Deactivate card" type="submit" variant="danger-soft" />
                                        </form>
                                        <form method="POST" action="{{ route('rfid-cards.block', $card) }}" onsubmit="return confirm('Block this RFID card?');">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-action icon="trash" label="Block card" type="submit" variant="danger-soft" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $member ? 6 : 7 }}">No RFID cards found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">{{ $cards->links() }}</div>
    </section>
@endsection
