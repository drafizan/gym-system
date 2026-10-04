@extends('layouts.app')

@section('title', 'Assign PT Package')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('pt.sessions.index') }}">Session Tracking</a>
    <span>Assign PT Package</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Personal Training</p>
            <h1>Assign PT Package</h1>
        </div>
    </div>

    <form method="POST" action="{{ route('pt.member-packages.store') }}" data-pt-package-assignment>
        <div class="registration-layout">
            <section class="registration-card">
                <h2>Member PT Package</h2>
            @csrf
            <div class="form-grid two-columns">
                <label class="form-row">
                    <span>Member</span>
                    <select name="member_id" required>
                        <option value="">Select active member</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>{{ $member->full_name }} · {{ $member->member_no }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-row">
                    <span>PT Package</span>
                    <select name="pt_package_id" required data-pt-package-select>
                        <option value="" data-price="">Select PT package</option>
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" data-price="{{ (float) $package->price }}" @selected(old('pt_package_id') == $package->id)>
                                {{ $package->name }} · {{ $package->sessions_count }} sessions · RM {{ number_format((float) $package->price, 2) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="form-row">
                    <span>Purchased Date</span>
                    <input type="date" name="purchased_at" value="{{ old('purchased_at', now()->toDateString()) }}" required>
                </label>
                <label class="form-row">
                    <span>Price (RM)</span>
                    <input type="number" name="price" min="0" step="0.01" value="{{ old('price') }}" data-pt-package-price placeholder="Auto from package">
                </label>
                <label class="form-row full-width">
                    <span>Notes</span>
                    <textarea name="notes" rows="4" placeholder="Optional">{{ old('notes') }}</textarea>
                </label>
            </div>
            <div class="form-actions">
                <a class="btn btn-light" href="{{ route('pt.sessions.index') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">Continue to POS</button>
            </div>
            </section>
            <section class="registration-card">
                <h2>POS Summary</h2>
                <div class="form-grid compact">
                    <div class="field form-span-2"><span>PT Package</span><strong data-pt-summary-total>RM {{ number_format((float) old('price'), 2) }}</strong></div>
                    <label class="field form-span-2"><span>Payment Method</span>
                        <select name="payment_method" required>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method }}" @selected(old('payment_method', 'cash') === $method)>{{ \App\Enums\PaymentMethod::labelFor($method) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="field form-span-2"><span>Total</span><strong data-pt-summary-total>RM {{ number_format((float) old('price'), 2) }}</strong></div>
                </div>
            </section>
        </div>
    </form>
@endsection
