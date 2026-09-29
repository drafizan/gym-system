@php
    $user = auth()->user();
    $userName = $user?->name ?? 'Admin';
    $userRole = $user?->role?->name ?? 'Administrator';
    $initials = collect(explode(' ', $userName))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<header class="app-header">
    <button class="icon-button menu-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
        <span></span>
    </button>

    <form class="header-search" method="GET" action="{{ route('search.index') }}">
        <span class="search-icon"></span>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search members, cards, receipts" aria-label="Search members, cards, receipts">
    </form>

    <div class="header-meta">
        <strong>{{ now()->format('d M Y') }}</strong>
        @if (config('gym.multibranch_enabled'))
            <small>{{ config('gym.branch_name') }}</small>
        @endif
    </div>

    <div class="header-actions">
        @if ($user?->hasPermission('sales.manage'))
            <a class="btn btn-primary header-quick-action" href="{{ route('sales.pos') }}" title="Open POS terminal">
                <svg class="header-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="8" cy="21" r="1"></circle>
                    <circle cx="19" cy="21" r="1"></circle>
                    <path d="M2.1 2.1h2l2.7 12.4a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L21 7H5.1"></path>
                </svg>
                <span>POS</span>
            </a>
        @endif
        @if ($user?->hasPermission('access.manage'))
            <form method="POST" action="{{ route('access.sync-now') }}">
                @csrf
                <button class="btn btn-primary header-quick-action" type="submit" title="Sync door access now">
                <svg class="header-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 12a9 9 0 0 1-9 9 9.8 9.8 0 0 1-6.4-2.4"></path>
                    <path d="M3 12a9 9 0 0 1 9-9 9.8 9.8 0 0 1 6.4 2.4"></path>
                    <path d="M16 5h2.9V2"></path>
                    <path d="M8 19H5.1v3"></path>
                </svg>
                <span>Sync Door Access Now</span>
                </button>
            </form>
        @endif
        <div class="user-chip">
            <span class="avatar">{{ $initials ?: 'AD' }}</span>
            <div>
                <strong>{{ $userName }}</strong>
                <small>{{ $userRole }}</small>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="icon-button logout-button" aria-label="Logout" title="Logout">
                <svg class="header-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <path d="M16 17l5-5-5-5"></path>
                    <path d="M21 12H9"></path>
                </svg>
            </button>
        </form>
    </div>
</header>
