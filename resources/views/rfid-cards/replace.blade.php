@extends('layouts.app')

@section('title', 'Update RFID Card')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    @if ($card->member)
        <a href="{{ route('members.show', $card->member) }}">{{ $card->member->member_no }}</a>
    @endif
    <span>Update RFID Card</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Access Control</p>
            <h1>Update RFID Card</h1>
        </div>
        <div class="toolbar-actions">
            @if ($card->member)
                <x-icon-action icon="back" label="Back to member profile" href="{{ route('members.show', $card->member) }}" />
            @else
                <x-icon-action icon="back" label="Back to RFID cards" href="{{ route('rfid-cards.index') }}" />
            @endif
        </div>
    </div>

    <section class="form-card">
        <div class="profile-card-header">
            <h2>Card Details</h2>
        </div>

        <div class="profile-info-table profile-info-table-wide">
            <div><span>Member</span><strong>{{ $card->member?->full_name ?? '-' }}</strong></div>
            <div><span>Member No.</span><strong>{{ $card->member?->member_no ?? '-' }}</strong></div>
            <div><span>Current Card</span><strong>{{ $card->card_number }}</strong></div>
            <div><span>Status</span><strong>{{ str($card->status)->headline() }}</strong></div>
        </div>

        @if (! $card->member || $card->member->status !== \App\Enums\RecordStatus::Active->value)
            <div class="alert alert-warning">RFID cards can only be activated for active members. Reactivate this member before updating the card.</div>
        @elseif (! $card->isActive())
            <div class="alert alert-warning">Only active RFID cards can be updated.</div>
        @else
            <form class="stacked-form" method="POST" action="{{ route('rfid-cards.store-replacement', $card) }}">
                @csrf

                <div class="form-grid two-columns">
                    <label class="field">
                        <span>Card Number</span>
                        <input type="text" name="card_number" value="{{ old('card_number', $card->card_number) }}" placeholder="Enter card number" required>
                        @error('card_number') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="field">
                        <span>Remarks</span>
                        <input type="text" name="remarks" value="{{ old('remarks', $card->remarks) }}" placeholder="Optional">
                        @error('remarks') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="form-actions">
                    <a class="btn btn-light" href="{{ $card->member ? route('members.show', $card->member) : route('rfid-cards.index') }}">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save Changes</button>
                </div>
            </form>
        @endif
    </section>
@endsection
