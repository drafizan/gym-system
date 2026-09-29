@extends('layouts.app')

@section('title', 'Assign RFID Card')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('members.show', $member) }}">{{ $member->member_no }}</a>
    <span>Assign RFID Card</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Access Control</p>
            <h1>Assign RFID Card</h1>
        </div>
        <div class="toolbar-actions">
            <x-icon-action icon="back" label="Back to member profile" href="{{ route('members.show', $member) }}" />
        </div>
    </div>

    <section class="form-card">
        <div class="profile-card-header">
            <h2>Card Details</h2>
        </div>

        <div class="profile-info-table profile-info-table-wide">
            <div><span>Member No.</span><strong>{{ $member->member_no }}</strong></div>
            <div><span>Name</span><strong>{{ $member->full_name }}</strong></div>
            <div><span>Phone</span><strong>{{ $member->phone ?: '-' }}</strong></div>
            <div><span>Current Card</span><strong>{{ $member->activeRfidCard?->card_number ?? 'Not assigned' }}</strong></div>
        </div>

        @if ($member->activeRfidCard)
            <div class="alert alert-warning">This member already has an active RFID card. Replace or deactivate the active card before assigning another card.</div>
        @else
            <form class="stacked-form" method="POST" action="{{ route('members.rfid-cards.store', $member) }}">
                @csrf

                <div class="form-grid two-columns">
                    <label class="field">
                        <span>Card Number</span>
                        <input type="text" name="card_number" value="{{ old('card_number') }}" placeholder="Enter card number manually" required>
                        @error('card_number') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="field">
                        <span>Remarks</span>
                        <input type="text" name="remarks" value="{{ old('remarks') }}" placeholder="Optional">
                        @error('remarks') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="form-actions">
                    <a class="btn btn-light" href="{{ route('members.show', $member) }}">Cancel</a>
                    <button class="btn btn-primary" type="submit">Assign Card</button>
                </div>
            </form>
        @endif
    </section>
@endsection
