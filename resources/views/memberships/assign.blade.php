@extends('layouts.app')

@section('title', $mode === 'renew' ? 'Renew Membership' : 'Assign Membership')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('members.show', $member) }}">{{ $member->member_no }}</a>
    <span>{{ $mode === 'renew' ? 'Renew Membership' : 'Assign Membership' }}</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Memberships</p>
            <h1>{{ $mode === 'renew' ? 'Renew Membership' : 'Assign Membership' }}</h1>
        </div>
    </div>

    <x-panel title="{{ $member->full_name }}">
        <form class="form-grid" method="POST" action="{{ $mode === 'renew' ? route('member-memberships.store-renewal', [$member, $membership]) : route('member-memberships.store', $member) }}">
            @csrf
            <label class="field">
                <span>Package</span>
                <select name="membership_package_id" required>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected((int) old('membership_package_id', $membership?->membership_package_id) === $package->id)>
                            {{ $package->name }} - RM {{ number_format((float) $package->price, 2) }} / {{ $package->duration_days }} days
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>{{ $mode === 'renew' ? 'Renewal Date' : 'Start Date' }}</span>
                <input type="date" name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
            </label>

            <label class="field">
                <span>Amount (RM)</span>
                <input type="number" min="0" step="0.01" name="amount" value="{{ old('amount', $membership?->package?->price ?? $packages->first()?->price ?? 0) }}">
            </label>

            <label class="field">
                <span>Payment Status</span>
                <select name="payment_status" required>
                    <option value="paid" @selected(old('payment_status', 'paid') === 'paid')>Paid</option>
                    <option value="unpaid" @selected(old('payment_status') === 'unpaid')>Unpaid</option>
                </select>
            </label>

            @if ($mode === 'renew' && $membership)
                <div class="form-span-2 field-help">
                    Current expiry: {{ $membership->end_date->format('d M Y') }}. If renewal is before expiry, the new expiry extends from this date.
                </div>
            @endif

            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('members.show', $member) }}">Cancel</a>
                <button class="btn btn-primary" type="submit">{{ $mode === 'renew' ? 'Renew Membership' : 'Assign Membership' }}</button>
            </div>
        </form>
    </x-panel>
@endsection
