@extends('layouts.app')

@section('title', 'PT Session Check-in')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('pt.schedule.index') }}">PT Schedule</a>
    <span>Session Check-in</span>
@endsection

@section('content')
    @php
        $selectedPackageIds = collect(old('pt_member_package_ids', old('pt_member_package_id') ? [old('pt_member_package_id')] : []))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values();
        $selectedMemberPackages = $memberPackages->filter(fn ($memberPackage) => $selectedPackageIds->contains((string) $memberPackage->id));
    @endphp

    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>PT Session Check-in</h1>
        </div>
    </div>

    <x-panel title="Check-in Details">
        <form method="POST" action="{{ route('pt.schedule.store') }}" data-pt-schedule-form>
            @csrf
            <input type="hidden" name="check_in" value="1">
            <input type="hidden" name="check_in_token" value="{{ old('check_in_token', (string) Str::uuid()) }}">
            <div class="form-grid two-columns pt-schedule-grid">
                <div class="form-row full-width">
                    <span>Eligible Members</span>
                    <div class="pt-schedule-member-picker">
                        <div class="filterable-combobox" data-filterable-combobox>
                            <select data-combobox-native data-pt-member-picker tabindex="-1" aria-hidden="true">
                                <option value="" data-filter="select eligible member">Select eligible member</option>
                                @foreach ($memberPackages as $memberPackage)
                                    @php
                                        $memberLabel = $memberPackage->member?->full_name.' · '.$memberPackage->member?->member_no;
                                        $memberMeta = $memberPackage->package?->name.' · '.$memberPackage->remainingSessions().' sessions left';
                                    @endphp
                                    <option
                                        value="{{ $memberPackage->id }}"
                                        data-filter="{{ str($memberLabel.' '.$memberPackage->member?->phone.' '.$memberMeta)->lower() }}"
                                        data-label="{{ $memberLabel }}"
                                        data-meta="{{ $memberMeta }}"
                                        data-remaining="{{ $memberPackage->remainingSessions() }}"
                                    >
                                        {{ $memberLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="combobox-trigger" type="button" data-combobox-trigger aria-haspopup="listbox" aria-expanded="false">
                                <span data-combobox-value>Select eligible member</span>
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M6 9l6 6 6-6"></path>
                                </svg>
                            </button>
                            <div class="combobox-panel" data-combobox-panel hidden>
                                <div class="combobox-search">
                                    <input type="search" data-combobox-search placeholder="Search name, member no., phone, or package">
                                </div>
                                <div class="combobox-options" data-combobox-options role="listbox">
                                    <button class="combobox-option" type="button" data-combobox-option data-value="" data-label="Select eligible member" data-filter="select eligible member" role="option">
                                        Select eligible member
                                    </button>
                                    @foreach ($memberPackages as $memberPackage)
                                        @php
                                            $memberLabel = $memberPackage->member?->full_name.' · '.$memberPackage->member?->member_no;
                                            $memberMeta = $memberPackage->package?->name.' · '.$memberPackage->remainingSessions().' sessions left';
                                        @endphp
                                        <button
                                            class="combobox-option"
                                            type="button"
                                            data-combobox-option
                                            data-value="{{ $memberPackage->id }}"
                                            data-label="{{ $memberLabel }}"
                                            data-filter="{{ str($memberLabel.' '.$memberPackage->member?->phone.' '.$memberMeta)->lower() }}"
                                            role="option"
                                        >
                                            <strong>{{ $memberLabel }}</strong>
                                            <small>{{ $memberPackage->member?->phone }} · {{ $memberMeta }}</small>
                                        </button>
                                    @endforeach
                                    <p class="combobox-empty" data-combobox-empty hidden>No eligible member found.</p>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-light" type="button" data-pt-member-add @disabled($memberPackages->isEmpty())>Add Member</button>
                    </div>
                    <div class="selected-member-list" data-pt-member-list>
                        @foreach ($selectedMemberPackages as $memberPackage)
                            <div class="selected-member-row" data-pt-member-item data-value="{{ $memberPackage->id }}">
                                <input type="hidden" name="pt_member_package_ids[]" value="{{ $memberPackage->id }}">
                                <div>
                                    <strong>{{ $memberPackage->member?->full_name }} · {{ $memberPackage->member?->member_no }}</strong>
                                    <small>{{ $memberPackage->package?->name }} · {{ $memberPackage->remainingSessions() }} sessions left</small>
                                </div>
                                <button class="icon-action danger" type="button" data-pt-member-remove aria-label="Remove member" title="Remove member">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18 6L6 18M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                        <p class="selected-member-empty" data-pt-member-empty @if ($selectedMemberPackages->isNotEmpty()) hidden @endif>
                            Add at least one eligible member with PT sessions remaining.
                        </p>
                    </div>
                    <small class="field-help">{{ $memberPackages->count() }} eligible {{ $memberPackages->count() === 1 ? 'package balance' : 'package balances' }} available</small>
                </div>
                <label class="form-row">
                    <span>Trainer</span>
                    <select name="trainer_id" required>
                        <option value="">Select trainer</option>
                        @foreach ($trainers as $trainer)
                            <option value="{{ $trainer->id }}" @selected(old('trainer_id') == $trainer->id)>{{ $trainer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-row">
                    <span>Date</span>
                    <input type="date" name="session_date" value="{{ old('session_date', now()->toDateString()) }}" required>
                </label>
                <label class="form-row">
                    <span>Start Time</span>
                    <input type="time" name="start_time" value="{{ old('start_time', now()->format('H:i')) }}" required>
                </label>
                <label class="form-row">
                    <span>Duration</span>
                    <input type="number" name="duration_minutes" min="15" max="240" step="15" value="{{ old('duration_minutes', 60) }}" required>
                </label>
                <label class="form-row full-width">
                    <span>Notes</span>
                    <textarea name="notes" rows="4" placeholder="Optional">{{ old('notes') }}</textarea>
                </label>
            </div>
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('pt.schedule.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Check In & Deduct Session</button>
            </div>
        </form>
    </x-panel>
@endsection
