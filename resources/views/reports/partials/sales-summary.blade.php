<section class="sales-kpi-grid" aria-label="Sales summary">
    <article class="sales-kpi-card"><div><span>Total Revenue</span><strong>RM {{ number_format((float) $summary['total_revenue'], 2) }}</strong><small>{{ $summary['transaction_count'] }} transactions</small></div></article>
    <article class="sales-kpi-card"><div><span>Membership Sales</span><strong>RM {{ number_format((float) $summary['membership_sales'], 2) }}</strong><small>Membership and renewal</small></div></article>
    <article class="sales-kpi-card"><div><span>Product Sales</span><strong>RM {{ number_format((float) $summary['product_sales'], 2) }}</strong><small>Retail counter</small></div></article>
    <article class="sales-kpi-card"><div><span>PT Sales</span><strong>RM {{ number_format((float) ($summary['pt_sales'] ?? 0), 2) }}</strong><small>Personal training packages</small></div></article>
    @if ((float) ($summary['other_sales'] ?? 0) > 0)
        <article class="sales-kpi-card"><div><span>Other Sales</span><strong>RM {{ number_format((float) $summary['other_sales'], 2) }}</strong><small>Uncategorized sales</small></div></article>
    @endif
    <article class="sales-kpi-card"><div><span>Cash Collection</span><strong>RM {{ number_format((float) ($payments['cash'] ?? 0), 2) }}</strong><small>Cash payments</small></div></article>
</section>
