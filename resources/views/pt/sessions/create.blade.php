@extends('layouts.app')

@section('title', 'Record PT Session')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('pt.sessions.index') }}">PT Sessions</a>
    <span>Record Session</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>Record PT Session</h1>
        </div>
    </div>

    <x-panel title="Session Details">
        <form method="POST" action="{{ route('pt.sessions.store') }}">
            @csrf
            <div class="form-grid two-columns">
                <label class="form-row full-width">
                    <span>Member PT Package</span>
                    <select name="pt_member_package_id" required>
                        <option value="">Select member package balance</option>
                        @foreach ($memberPackages as $memberPackage)
                            <option value="{{ $memberPackage->id }}" @selected(old('pt_member_package_id') == $memberPackage->id)>
                                {{ $memberPackage->member?->full_name }} · {{ $memberPackage->package?->name }} · {{ $memberPackage->remainingSessions() }} remaining
                            </option>
                        @endforeach
                    </select>
                </label>
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
                    <span>Session Date</span>
                    <input type="date" name="session_date" value="{{ old('session_date', now()->toDateString()) }}" required>
                </label>
                <label class="form-row">
                    <span>Duration</span>
                    <input type="number" name="duration_minutes" min="15" max="240" step="15" value="{{ old('duration_minutes', 60) }}" required>
                </label>
                <label class="form-row">
                    <span>Status</span>
                    <select name="status" required>
                        <option value="completed" @selected(old('status', 'completed') === 'completed')>Completed</option>
                        <option value="cancelled" @selected(old('status') === 'cancelled')>Cancelled</option>
                    </select>
                </label>
                <label class="form-row full-width">
                    <span>Notes</span>
                    <textarea name="notes" rows="4" placeholder="Optional">{{ old('notes') }}</textarea>
                </label>
            </div>
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('pt.sessions.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Record Session</button>
            </div>
        </form>
    </x-panel>
@endsection
