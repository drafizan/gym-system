@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Reports</a>
    <span>Daily Sales Report</span>
@endsection

@section('content')
    @php
        $canDeploy = auth()->user()?->username === 'admin';
        $reportDate = now()->format('d/m/Y');
        $summary = $report['summary'] ?? [];
        $payments = $report['payment_breakdown'] ?? [];
        $transactions = $report['transactions'] ?? [];
        $totalRevenue = (float) ($summary['total_revenue'] ?? 0);
        $membershipSales = (float) ($summary['membership_sales'] ?? 0);
        $productSales = (float) ($summary['product_sales'] ?? 0);
        $ptSales = (float) ($summary['pt_sales'] ?? 0);
        $otherSales = (float) ($summary['other_sales'] ?? max(0, $totalRevenue - $membershipSales - $productSales - $ptSales));
        $hasOtherSales = collect($transactions)->contains(fn (array $transaction): bool => ($transaction['category'] ?? null) === 'other');
        $cashCollection = (float) ($payments['cash'] ?? 0);
        $onlinePayment = (float) (($payments['qr'] ?? 0) + ($payments['debit_credit_card'] ?? 0));
        $pt = $dashboard['personal_training'] ?? [];
        $paymentTotal = $cashCollection + $onlinePayment;
        $percent = fn (float $amount): string => $totalRevenue > 0 ? number_format($amount / $totalRevenue * 100, 1).'%' : '0.0%';
        $paymentPercent = fn (float $amount): string => $paymentTotal > 0 ? number_format($amount / $paymentTotal * 100, 1).'%' : '0.0%';
        $membershipStop = $totalRevenue > 0 ? round($membershipSales / $totalRevenue * 100, 1) : 0;
        $productStop = $totalRevenue > 0 ? round(($membershipSales + $productSales) / $totalRevenue * 100, 1) : 0;
        $ptStop = $totalRevenue > 0 ? round(($membershipSales + $productSales + $ptSales) / $totalRevenue * 100, 1) : 0;
        $otherStop = $totalRevenue > 0 ? round(($membershipSales + $productSales + $ptSales + $otherSales) / $totalRevenue * 100, 1) : 0;
        $cashStop = $paymentTotal > 0 ? round($cashCollection / $paymentTotal * 100, 1) : 0;
        $weeklyChart = collect($dashboard['weekly_sales_chart'] ?? []);
        $weeklyTotals = $weeklyChart->map(fn (array $day): float => (float) ($day['membership_sales'] ?? 0) + (float) ($day['product_sales'] ?? 0) + (float) ($day['pt_sales'] ?? 0))->values();
        $weeklyTotalSales = (float) $weeklyTotals->sum();
        $weeklyMax = max(1, (float) $weeklyTotals->max());
        $chartWidth = 640;
        $chartHeight = 180;
        $chartTop = 18;
        $chartBottom = 168;
        $chartPointPairs = $weeklyTotals->map(function (float $total, int $index) use ($weeklyTotals, $weeklyMax, $chartWidth, $chartTop, $chartBottom): array {
            $x = $weeklyTotals->count() > 1 ? $index * ($chartWidth / ($weeklyTotals->count() - 1)) : 0;
            $y = $chartBottom - (($total / $weeklyMax) * ($chartBottom - $chartTop));
            $safeY = round(min($chartBottom, max($chartTop, $y)), 1);

            return [
                'x' => round($x, 1),
                'y' => $safeY,
                'label_x' => round(min($chartWidth - 34, max(34, $x)), 1),
                'label_y' => max(12, $safeY - 12),
                'amount' => $total,
            ];
        });
        $chartPoints = $chartPointPairs->map(fn (array $point): string => $point['x'].','.$point['y'])->join(' ');
        $chartLabels = $weeklyChart->map(fn (array $day): string => \Illuminate\Support\Carbon::parse($day['date'])->format('D'))->values();
        $chartScale = collect([1, 0.75, 0.5, 0.25, 0])->map(fn (float $level): string => 'RM '.number_format($weeklyMax * $level, 0));
        $systemStatus = $dashboard['system_status'] ?? [];
        $controllerStatuses = collect($systemStatus['controller_statuses'] ?? []);
        $pendingAccessSyncs = (int) ($systemStatus['pending_access_syncs'] ?? 0);
        $lastAccessSync = filled($systemStatus['last_access_sync_at'] ?? null)
            ? \Illuminate\Support\Carbon::parse($systemStatus['last_access_sync_at'])->format('d M Y, H:i')
            : 'Not synced yet';
        $lastAccessSyncStatus = $systemStatus['last_access_sync_status'] ?? null;
    @endphp

    <div class="page-toolbar">
        <div>
            <h1>Daily Sales Report</h1>
        </div>
        <div class="toolbar-actions">
            @if ($canDeploy)
                <x-icon-action icon="refresh" label="Update deployment" data-modal-open="deployment-update" />
            @endif
        </div>
    </div>

    @if (session('deployment_output'))
        <section class="deployment-output" aria-label="Deployment output">
            <div class="deployment-output-header">
                <strong>Deployment Output</strong>
                <span>{{ now()->format('d M Y, h:i A') }}</span>
            </div>
            <pre>{{ session('deployment_output') }}</pre>
        </section>
    @endif

    <section @class(['report-filter-panel', 'is-single-branch' => ! config('gym.multibranch_enabled')]) aria-label="Daily sales filters">
        <div class="report-field">
            <label for="report-date">Date</label>
            <input id="report-date" type="text" value="{{ $reportDate }}" readonly>
        </div>

        @if (config('gym.multibranch_enabled'))
            <div class="report-field">
                <label for="report-outlet">Outlet</label>
                <select id="report-outlet">
                    <option>All Outlets</option>
                </select>
            </div>
        @endif

        <div class="report-filter-actions">
            <button class="btn btn-primary" type="button">
                <span class="button-icon refresh"></span>
                Generate Report
            </button>
            <x-icon-action icon="download" label="Export report" />
        </div>
    </section>

    <section class="sales-kpi-grid" aria-label="Sales summary">
        <article class="sales-kpi-card">
            <span class="sales-kpi-icon wallet">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 7H5a3 3 0 0 0 0 6h15a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1Z"></path>
                    <path d="M5 13h15a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h12"></path>
                    <circle cx="17" cy="10" r="1"></circle>
                </svg>
            </span>
            <div>
                <span>Total Revenue</span>
                <strong>RM {{ number_format($totalRevenue, 2) }}</strong>
                <small>{{ $summary['transaction_count'] ?? 0 }} transactions today</small>
            </div>
        </article>
        <article class="sales-kpi-card">
            <span class="sales-kpi-icon member">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9.5" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.9"></path>
                    <path d="M16 3.1a4 4 0 0 1 0 7.8"></path>
                </svg>
            </span>
            <div>
                <span>Membership Sales</span>
                <strong>RM {{ number_format($membershipSales, 2) }}</strong>
                <small>{{ $percent($membershipSales) }} of total</small>
            </div>
        </article>
        <article class="sales-kpi-card">
            <span class="sales-kpi-icon product">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 8h12l-1 13H7L6 8Z"></path>
                    <path d="M9 8a3 3 0 0 1 6 0"></path>
                    <path d="M9 12h.01"></path>
                    <path d="M15 12h.01"></path>
                </svg>
            </span>
            <div>
                <span>Product Sales</span>
                <strong>RM {{ number_format($productSales, 2) }}</strong>
                <small>{{ $percent($productSales) }} of total</small>
            </div>
        </article>
        <article class="sales-kpi-card">
            <span class="sales-kpi-icon pt">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6.5 6.5v11"></path>
                    <path d="M17.5 6.5v11"></path>
                    <path d="M6.5 12h11"></path>
                    <circle cx="12" cy="4" r="2"></circle>
                </svg>
            </span>
            <div>
                <span>PT Sales</span>
                <strong>RM {{ number_format($ptSales, 2) }}</strong>
                <small>{{ $percent($ptSales) }} of total</small>
            </div>
        </article>
        <article class="sales-kpi-card">
            <span class="sales-kpi-icon cash">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="6" width="18" height="12" rx="2"></rect>
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M6 9v.01"></path>
                    <path d="M18 15v.01"></path>
                </svg>
            </span>
            <div>
                <span>Total Daily Sales</span>
                <strong>RM {{ number_format($totalRevenue, 2) }}</strong>
                <small>{{ $summary['transaction_count'] ?? 0 }} transactions today</small>
            </div>
        </article>
    </section>

    <section class="report-chart-grid">
        <article class="report-card">
            <h2>Sales Breakdown</h2>
            <div class="breakdown-layout">
                @if ($totalRevenue > 0)
                    <div class="donut-chart sales-donut" style="--membership-stop: {{ $membershipStop }}%; --product-stop: {{ $productStop }}%; --pt-stop: {{ $ptStop }}%; --other-stop: {{ $otherStop }}%;">
                        <span>RM<br><strong>{{ number_format($totalRevenue, 2) }}</strong></span>
                    </div>
                @else
                    <div class="chart-empty-state">Chart not available</div>
                @endif
                <div class="legend-list">
                    <div><span class="legend-dot blue"></span><strong>Membership Sales</strong><small>RM {{ number_format($membershipSales, 2) }} ({{ $percent($membershipSales) }})</small></div>
                    <div><span class="legend-dot green"></span><strong>Product Sales</strong><small>RM {{ number_format($productSales, 2) }} ({{ $percent($productSales) }})</small></div>
                    <div><span class="legend-dot purple"></span><strong>PT Sales</strong><small>RM {{ number_format($ptSales, 2) }} ({{ $percent($ptSales) }})</small></div>
                    @if ($hasOtherSales)
                        <div><span class="legend-dot orange"></span><strong>Other Sales</strong><small>RM {{ number_format($otherSales, 2) }} ({{ $percent($otherSales) }})</small></div>
                    @endif
                </div>
            </div>
        </article>

        <article class="report-card">
            <h2>Payment Method Breakdown</h2>
            <div class="breakdown-layout">
                @if ($paymentTotal > 0)
                    <div class="donut-chart payment-donut" style="--cash-stop: {{ $cashStop }}%;">
                        <span>RM<br><strong>{{ number_format($paymentTotal, 2) }}</strong></span>
                    </div>
                @else
                    <div class="chart-empty-state">Chart not available</div>
                @endif
                <div class="legend-list">
                    <div><span class="legend-dot blue"></span><strong>Cash</strong><small>RM {{ number_format($cashCollection, 2) }} ({{ $paymentPercent($cashCollection) }})</small></div>
                    <div><span class="legend-dot green"></span><strong>Online Payment</strong><small>RM {{ number_format($onlinePayment, 2) }} ({{ $paymentPercent($onlinePayment) }})</small></div>
                </div>
            </div>
        </article>

        <article class="report-card trend-card">
            <h2>Weekly Sales Trend</h2>
            @if ($weeklyTotalSales > 0)
                <div class="line-chart" aria-label="Weekly sales trend">
                    <div class="chart-grid-lines"></div>
                    <svg viewBox="0 0 640 180" role="img" aria-hidden="true">
                        <polyline points="{{ $chartPoints }}"></polyline>
                        @foreach ($chartPointPairs as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5"></circle>
                            <text class="chart-value-label" x="{{ $point['label_x'] }}" y="{{ $point['label_y'] }}">RM {{ number_format($point['amount'], 0) }}</text>
                        @endforeach
                    </svg>
                    <div class="chart-y-axis">
                        @foreach ($chartScale as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="chart-x-axis">
                        @foreach ($chartLabels as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="chart-empty-state">Chart not available</div>
            @endif
        </article>
    </section>

    <section class="sales-details-card">
        <div class="sales-details-header">
            <h2>Sales Details</h2>
            <div class="sales-search">
                <input type="search" placeholder="Search transaction...">
                <button class="icon-button" type="button" aria-label="Filter sales">
                    <span class="filter-icon"></span>
                </button>
            </div>
        </div>

        <div class="sales-tabs" role="tablist" aria-label="Sales detail filters" data-sales-tabs>
            <button class="active" type="button" data-sales-filter="all">All Transactions</button>
            <button type="button" data-sales-filter="membership">Membership Sales</button>
            <button type="button" data-sales-filter="product">Product Sales</button>
            <button type="button" data-sales-filter="pt">PT Sales</button>
            @if ($hasOtherSales)
                <button type="button" data-sales-filter="other">Other Sales</button>
            @endif
        </div>

        @include('reports.partials.sales-table', ['transactions' => $transactions, 'filterable' => true])

        <div class="sales-pagination" data-sales-pagination data-page-size="8">
            <span data-sales-pagination-status>Showing 0 of {{ count($transactions) }} entries</span>
            <div data-sales-pagination-buttons>
                <button type="button" data-sales-page="prev" aria-label="Previous page">Prev</button>
                <button type="button" data-sales-page="next" aria-label="Next page">Next</button>
            </div>
        </div>
    </section>

    <section class="report-card door-dashboard-card" aria-label="Door access status">
        <div class="door-dashboard-header">
            <div>
                <h2>Door Access Status</h2>
                <p>Device connection and sync queue.</p>
            </div>
            <dl class="door-dashboard-meta">
                <div>
                    <dt>Last Sync</dt>
                    <dd>{{ $lastAccessSync }}</dd>
                    <small @class([
                        'text-success' => $lastAccessSyncStatus === 'success',
                        'text-warning' => $lastAccessSyncStatus === null,
                        'text-danger' => $lastAccessSyncStatus === 'failed',
                    ])>
                        {{ $lastAccessSyncStatus ? str($lastAccessSyncStatus)->headline() : 'Pending first sync' }}
                    </small>
                </div>
                <div>
                    <dt>Pending Syncs</dt>
                    <dd>{{ number_format($pendingAccessSyncs) }}</dd>
                    <small @class(['text-warning' => $pendingAccessSyncs > 0, 'text-success' => $pendingAccessSyncs === 0])>
                        {{ $pendingAccessSyncs > 0 ? 'Awaiting device update' : 'No pending sync' }}
                    </small>
                </div>
            </dl>
        </div>

        <div class="door-controller-grid">
            @forelse ($controllerStatuses as $controller)
                <article class="door-controller-row">
                    <div>
                        <span class="status-pill {{ $controller['online'] ? 'online' : 'offline' }}">{{ $controller['label'] }}</span>
                        <small>{{ $controller['host'] ?: 'No IP configured' }}{{ $controller['host'] ? ':'.$controller['port'] : '' }}</small>
                    </div>
                    <div>
                        <span>Last Sync</span>
                        <strong>{{ $controller['last_sync_label'] }}</strong>
                        <small @class([
                            'text-success' => ($controller['last_sync_status'] ?? null) === 'success',
                            'text-danger' => ($controller['last_sync_status'] ?? null) === 'failed',
                            'text-warning' => blank($controller['last_sync_status'] ?? null),
                        ])>
                            {{ filled($controller['last_sync_status'] ?? null) ? str($controller['last_sync_status'])->headline() : 'Not synced yet' }}
                        </small>
                    </div>
                </article>
            @empty
                <div class="door-controller-empty">
                    Door access is not configured yet.
                </div>
            @endforelse
        </div>
    </section>

    @if ($canDeploy)
        <div class="modal-backdrop" data-modal="deployment-update" aria-hidden="true">
            <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="deployment-update-title">
                <button type="button" class="modal-close" data-modal-close aria-label="Close deployment modal"></button>

                <span class="badge">Deployment</span>
                <h2 id="deployment-update-title">Update from GitHub</h2>
                <p>
                    This runs the approved deployment script on the server. Use it only after the latest
                    GitHub version is ready to go live.
                </p>

                <form class="modal-form" method="POST" action="{{ route('deployment.update') }}">
                    @csrf

                    <label class="field">
                        <span>Deployment key</span>
                        <input
                            type="password"
                            name="deployment_key"
                            autocomplete="off"
                            maxlength="255"
                            placeholder="Enter deployment key"
                            required
                        >
                    </label>

                    <button class="btn btn-primary" type="submit">Run Update</button>
                </form>
            </section>
        </div>
    @endif
@endsection
