@php
    $menuSections = [
        [
            'label' => 'Main',
            'items' => [
                ['type' => 'link', 'icon' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'permission' => 'dashboard.view'],
            ],
        ],
        [
            'label' => 'Gym Operations',
            'items' => [
                [
                    'type' => 'group',
                    'icon' => 'users',
                    'label' => 'Members',
                    'permission' => 'members.manage',
                    'route' => 'members.index',
                    'children' => [
                        ['label' => 'Member Registration', 'route' => 'members.create'],
                        ['label' => 'Expiring Soon', 'route' => 'members.expiring-soon'],
                        ['label' => 'Expired Members', 'route' => 'members.expired'],
                        ['label' => 'Photo Capture', 'route' => 'members.photo-capture'],
                    ],
                ],
                [
                    'type' => 'group',
                    'icon' => 'membership',
                    'label' => 'Memberships',
                    'permission' => 'memberships.manage',
                    'children' => [
                        ['label' => 'Packages', 'route' => 'membership-packages.index'],
                    ],
                ],
                [
                    'type' => 'group',
                    'icon' => 'access',
                    'label' => 'Access Control',
                    'permission' => 'access.manage',
                    'route' => 'rfid-cards.index',
                    'children' => [
                        ['label' => 'Card History', 'route' => 'rfid-cards.history'],
                        ['label' => 'Door Event History', 'route' => 'door-access.history'],
                        ['label' => 'Door Configuration', 'route' => 'settings.index'],
                    ],
                ],
                [
                    'type' => 'group',
                    'icon' => 'sales',
                    'label' => 'Sales',
                    'permission' => 'sales.manage',
                    'children' => [
                        ['label' => 'POS Terminal', 'route' => 'sales.pos'],
                        ['label' => 'Sales History', 'route' => 'sales.history'],
                    ],
                ],
                [
                    'type' => 'group',
                    'icon' => 'trainer',
                    'label' => 'Personal Training',
                    'permission' => 'pt.manage',
                    'route' => 'pt.schedule.index',
                    'children' => [
                        ['label' => 'Trainers', 'route' => 'pt.trainers.index', 'hide_for_cashier' => true],
                        ['label' => 'PT Packages', 'route' => 'pt.packages.index', 'hide_for_cashier' => true],
                        ['label' => 'Assign Package', 'route' => 'pt.member-packages.create'],
                        ['label' => 'Schedule', 'route' => 'pt.schedule.index'],
                        ['label' => 'Session Tracking', 'route' => 'pt.sessions.index'],
                        ['label' => 'Commission Report', 'route' => 'pt.reports.commission', 'hide_for_cashier' => true],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Management',
            'items' => [
                [
                    'type' => 'group',
                    'icon' => 'products',
                    'label' => 'Products',
                    'permission' => 'products.manage',
                    'route' => 'products.index',
                    'children' => [
                        ['label' => 'Categories', 'route' => 'product-categories.index'],
                        ['label' => 'Low Stock', 'route' => 'products.low-stock'],
                        ['label' => 'Price Changes', 'route' => 'products.price-changes'],
                    ],
                ],
                [
                    'type' => 'group',
                    'icon' => 'reports',
                    'label' => 'Reports',
                    'permission' => 'reports.view',
                    'children' => [
                        ['label' => 'Daily Sales', 'route' => 'reports.daily-sales'],
                    ],
                ],
                [
                    'type' => 'group',
                    'icon' => 'roles',
                    'label' => 'Users & Roles',
                    'permission' => 'users.manage',
                    'route' => 'users.index',
                    'children' => [
                        ['label' => 'Users', 'route' => 'users.index'],
                        ['label' => 'Permissions', 'route' => 'users.permissions'],
                        ['label' => 'Password Reset', 'route' => 'users.password-resets'],
                    ],
                ],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                ['type' => 'link', 'icon' => 'backup', 'label' => 'Backup & Restore', 'route' => 'backups.index', 'meta' => 'Daily', 'permission' => 'backup.manage'],
                ['type' => 'link', 'icon' => 'audit', 'label' => 'Audit Trail', 'route' => 'audit.index', 'permission' => 'audit.view'],
                ['type' => 'link', 'icon' => 'settings', 'label' => 'Settings', 'route' => 'settings.index', 'permission' => 'settings.manage'],
            ],
        ],
    ];

    $user = auth()->user();
    $systemSettings = app(\App\Support\SystemSettings::class);
@endphp

<aside class="app-sidebar" data-sidebar>
    <div class="sidebar-brand">
        <div class="sidebar-brand-copy">
            <strong>{{ $systemSettings->get('gym_name') }}</strong>
            <small>Membership and Access Control System (MACS)</small>
        </div>
        <button class="sidebar-collapse-button" type="button" data-sidebar-collapse aria-label="Collapse sidebar"></button>
    </div>

    <nav class="sidebar-menu" aria-label="Main navigation">
        @foreach ($menuSections as $section)
            @php
                $visibleItems = collect($section['items'])
                    ->filter(fn ($item) => empty($item['permission']) || $user?->hasPermission($item['permission']));
            @endphp

            @continue($visibleItems->isEmpty())

            <div class="menu-section">
                <p class="menu-heading">{{ $section['label'] }}</p>

                @foreach ($visibleItems as $item)
                    @if ($item['type'] === 'link')
                        @php
                            $isActive = ! empty($item['active']) || (! empty($item['route']) && request()->routeIs($item['route']));
                        @endphp
                        <a href="{{ ! empty($item['route']) ? route($item['route']) : '#' }}" class="menu-link {{ $isActive ? 'active' : '' }}" data-tooltip="{{ $item['label'] }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                            <span class="menu-icon"><x-menu-icon :name="$item['icon']" /></span>
                            <span class="menu-text">{{ $item['label'] }}</span>
                            @isset($item['badge'])
                                <span class="menu-badge">{{ $item['badge'] }}</span>
                            @endisset
                            @isset($item['meta'])
                                <span class="menu-meta">{{ $item['meta'] }}</span>
                            @endisset
                        </a>
                    @else
                        @php
                            $isParentActive = ! empty($item['route']) && request()->routeIs($item['route']);
                            $isChildGroupActive = collect($item['children'] ?? [])->contains(function ($child): bool {
                                $childRoute = is_array($child) ? ($child['route'] ?? null) : null;

                                return $childRoute && request()->routeIs($childRoute);
                            });
                            $isGroupActive = $isParentActive || $isChildGroupActive;
                        @endphp
                        <div class="menu-group {{ (! empty($item['open']) || $isGroupActive) ? 'is-open' : '' }}" data-menu-group>
                            <button type="button" class="menu-link menu-trigger" data-menu-trigger data-tooltip="{{ $item['label'] }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                                <span class="menu-icon"><x-menu-icon :name="$item['icon']" /></span>
                                <span class="menu-text">{{ $item['label'] }}</span>
                                @isset($item['badge'])
                                    <span class="menu-badge">{{ $item['badge'] }}</span>
                                @endisset
                                <span class="menu-caret"></span>
                            </button>

                            <div class="submenu">
                                @if (! empty($item['route']))
                                    <a href="{{ route($item['route']) }}" class="submenu-link {{ $isParentActive ? 'active' : '' }}">
                                        <span class="submenu-dot"></span>
                                        Manage {{ $item['label'] }}
                                    </a>
                                @endif
                                @foreach ($item['children'] as $child)
                                    @continue(is_array($child) && ! empty($child['hide_for_cashier']) && $user?->role?->name === 'cashier')
                                    @php
                                        $childLabel = is_array($child) ? $child['label'] : $child;
                                        $childRoute = is_array($child) ? ($child['route'] ?? null) : null;
                                        $isChildActive = $childRoute && request()->routeIs($childRoute);
                                    @endphp
                                    <a href="{{ $childRoute ? route($childRoute) : '#' }}" class="submenu-link {{ $isChildActive ? 'active' : '' }}">
                                        <span class="submenu-dot"></span>
                                        {{ $childLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    </nav>

    @php
        $sidebarDoorStatuses = \App\Models\AccessControllerSetting::query()
            ->whereIn('name', ['1st Floor Door', '2nd Floor Door', 'Door Access Unit 1', 'Door Access Unit 2'])
            ->orderBy('id')
            ->get()
            ->map(fn (\App\Models\AccessControllerSetting $unit): array => [
                'label' => $unit->statusLabel(),
                'online' => $unit->isOnline(),
            ])
            ->values();

        if ($sidebarDoorStatuses->isEmpty()) {
            $sidebarDoorStatuses = collect([[
                'label' => 'Door access not configured',
                'online' => false,
            ]]);
        }

        $bridgeStatus = app(\App\Support\DahuaBridgeHeartbeat::class)->status();
        $bridgeOnline = (bool) ($bridgeStatus['healthy'] ?? false);
    @endphp

    <div class="sidebar-card">
        <div class="door-status-list">
            @foreach ($sidebarDoorStatuses as $doorStatus)
                <span class="status-pill {{ $doorStatus['online'] ? 'online' : 'offline' }}">{{ $doorStatus['label'] }}</span>
            @endforeach
            <span class="status-pill {{ $bridgeOnline ? 'online' : 'offline' }}">
                Bridge {{ $bridgeOnline ? 'Online' : 'Offline' }}
            </span>
        </div>
        <strong>Door Access</strong>
        <small>Door and bridge sync status.</small>
    </div>

    <div class="sidebar-footer">
        <a href="#">{{ config('gym.system_version') }}</a>
    </div>
</aside>
