@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <main class="auth-page">
        <section class="auth-panel" aria-labelledby="login-title">
            <div class="auth-brand">
                <div class="auth-brand-copy">
                    <strong>Gorilla Mutantz Gym Sdn Bhd</strong>
                    <small>Membership and Access Control System (MACS)</small>
                </div>
            </div>

            <div class="auth-heading">
                <span>Staff login</span>
                <h1 id="login-title">Sign in to MACS</h1>
                <p>Use your staff account to open the counter system.</p>
            </div>

            @include('partials.flash')
            @include('partials.validation-errors')

            <form class="auth-form" method="POST" action="{{ route('login.store') }}">
                @csrf

                <label class="field">
                    <span>Username</span>
                    <input
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        autocomplete="username"
                        placeholder="admin"
                        maxlength="100"
                        required
                        autofocus
                    >
                </label>

                <label class="field">
                    <span>Password</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Enter password"
                        maxlength="255"
                        required
                    >
                </label>

                <div class="auth-options">
                    <label class="check-field">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>

                    <button type="button" class="link-button" data-modal-open="support-reset">
                        Forgot password?
                    </button>
                </div>

                <button class="btn btn-primary auth-submit" type="submit">Sign In</button>
            </form>
        </section>

        <aside class="auth-side" aria-label="System status">
            <div class="auth-side-content">
                <div class="door-status-list" aria-label="Door status">
                    @forelse ($loginStatus['door_statuses'] as $doorStatus)
                        <span class="badge {{ $doorStatus['online'] ? 'status-success' : 'status-danger' }}">{{ $doorStatus['label'] }}</span>
                    @empty
                        <span class="badge status-muted">Door access not configured</span>
                    @endforelse
                </div>
                <h2>Counter status</h2>
                <div class="auth-metrics">
                    <div>
                        <strong>{{ number_format($loginStatus['active_access']) }}</strong>
                        <span>Active Door Access</span>
                    </div>
                    <div>
                        <strong>{{ $loginStatus['last_sync'] }}</strong>
                        <span>Last sync</span>
                    </div>
                    <div>
                        <strong>{{ number_format($loginStatus['pending_syncs']) }}</strong>
                        <span>Pending syncs</span>
                    </div>
                </div>
            </div>
        </aside>
    </main>

    <div class="modal-backdrop" data-modal="support-reset" aria-hidden="true">
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="support-reset-title">
            <button type="button" class="modal-close" data-modal-close aria-label="Close support reset modal"></button>

            <span class="badge">Password support</span>
            <h2 id="support-reset-title">Contact support to reset password</h2>
            <p>
                For security, password resets are handled by the system administrator.
                Contact support on WhatsApp and include your staff name and branch.
            </p>

            <div class="support-list">
                <div>
                    <span>WhatsApp</span>
                    <a href="https://wa.me/601128520309" target="_blank" rel="noopener">+601128520309</a>
                </div>
            </div>

            <button type="button" class="btn btn-primary" data-modal-close>Done</button>
        </section>
    </div>
@endsection
