@php
    $systemSettings = app(\App\Support\SystemSettings::class);
@endphp

<footer class="app-footer">
    <span>© 2026 {{ $systemSettings->get('company_name') }}.</span>
    @if (config('gym.multibranch_enabled'))
        <span>{{ config('gym.branch_name') }}</span>
    @endif
    <span>{{ config('gym.system_version') }}</span>
</footer>
