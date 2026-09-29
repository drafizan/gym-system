<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\PtMemberPackage;
use App\Models\PtSession;
use App\Models\PtTrainer;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        return [
            'kpis' => $this->kpis(),
            'membership_status_overview' => $this->membershipStatusOverview(),
            'weekly_sales_chart' => $this->weeklySalesChart(),
            'recent_activity' => $this->recentActivity(),
            'system_status' => $this->systemStatus(),
            'personal_training' => $this->personalTraining(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $todaySales = $this->categorizedSalesTotals($todayStart, $todayEnd);

        return [
            'total_members' => Member::query()->count(),
            'active_members' => Member::query()->where('status', RecordStatus::Active->value)->count(),
            'expired_members' => $this->expiredMembershipQuery()->distinct('member_id')->count('member_id'),
            'expiring_soon' => $this->expiringSoonMembershipQuery()->distinct('member_id')->count('member_id'),
            'todays_sales' => $todaySales['total'],
            'membership_sales' => $todaySales['membership'],
            'product_sales' => $todaySales['product'],
            'pt_sales' => $todaySales['pt'],
            'walk_in_sales' => $todaySales['walk_in'],
            'monthly_revenue' => $this->completedSalesQuery()
                ->whereBetween('completed_at', [$monthStart, $monthEnd])
                ->sum('total'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function membershipStatusOverview(): array
    {
        return [
            MembershipStatus::Active->value => MemberMembership::query()
                ->where('status', MembershipStatus::Active->value)
                ->whereDate('end_date', '>=', now()->toDateString())
                ->count(),
            MembershipStatus::ExpiringSoon->value => $this->expiringSoonMembershipQuery()->count(),
            MembershipStatus::Expired->value => $this->expiredMembershipQuery()->count(),
            MembershipStatus::Suspended->value => MemberMembership::query()->where('status', MembershipStatus::Suspended->value)->count(),
            MembershipStatus::Cancelled->value => MemberMembership::query()->where('status', MembershipStatus::Cancelled->value)->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weeklySalesChart(): array
    {
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        $days = collect(range(0, 6))->mapWithKeys(function (int $offset) use ($start): array {
            $date = $start->copy()->addDays($offset);

            return [$date->toDateString() => [
                'date' => $date->toDateString(),
                'membership_sales' => 0.0,
                'product_sales' => 0.0,
                'pt_sales' => 0.0,
                'walk_in_sales' => 0.0,
            ]];
        });

        $this->completedSalesQuery()
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('DATE(completed_at) as sale_date, sale_type, COALESCE(SUM(total), 0) as total')
            ->groupByRaw('DATE(completed_at), sale_type')
            ->get()
            ->each(function ($row) use ($days): void {
                $date = (string) $row->sale_date;

                if (! $days->has($date)) {
                    return;
                }

                $day = $days->get($date);
                $total = (float) $row->total;

                if (in_array($row->sale_type, $this->membershipSaleTypes(), true)) {
                    $day['membership_sales'] += $total;
                } elseif ($row->sale_type === SaleType::ProductSale->value) {
                    $day['product_sales'] += $total;
                } elseif ($row->sale_type === SaleType::PtSession->value) {
                    $day['pt_sales'] += $total;
                }

                if ($row->sale_type === SaleType::WalkInSale->value) {
                    $day['walk_in_sales'] += $total;
                }

                $days->put($date, $day);
            });

        $this->personalTrainingProductSalesQuery()
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('DATE(completed_at) as sale_date, COALESCE(SUM(total), 0) as total')
            ->groupByRaw('DATE(completed_at)')
            ->get()
            ->each(function ($row) use ($days): void {
                $date = (string) $row->sale_date;

                if (! $days->has($date)) {
                    return;
                }

                $day = $days->get($date);
                $total = (float) $row->total;
                $day['product_sales'] = max(0, $day['product_sales'] - $total);
                $day['pt_sales'] += $total;

                $days->put($date, $day);
            });

        return $days->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return AuditLog::query()
            ->with('user')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'module' => $log->module,
                'action' => $log->action,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function systemStatus(): array
    {
        $controllers = AccessControllerSetting::query()
            ->where('is_enabled', true)
            ->latest('last_sync_at')
            ->get();
        $lastSyncedController = $controllers->first(fn (AccessControllerSetting $controller): bool => $controller->last_sync_at !== null)
            ?? $controllers->first();

        return [
            'database' => $this->databaseIsOnline() ? 'online' : 'offline',
            'backup_path' => $this->backupPathStatus(),
            'controller' => $controllers->isNotEmpty() ? 'configured' : 'not_configured',
            'controller_units' => $controllers->count(),
            'controller_names' => $controllers
                ->map(fn (AccessControllerSetting $controller): string => $controller->displayName())
                ->all(),
            'controller_statuses' => $controllers
                ->sortBy('id')
                ->map(fn (AccessControllerSetting $controller): array => [
                    'name' => $controller->displayName(),
                    'label' => $controller->statusLabel(),
                    'online' => $controller->isOnline(),
                    'host' => $controller->host,
                    'port' => $controller->effectivePort(),
                    'last_sync_at' => $controller->last_sync_at?->toDateTimeString(),
                    'last_sync_label' => $controller->last_sync_at?->format('d M Y, H:i') ?? 'Not synced yet',
                    'last_sync_status' => $controller->last_sync_status,
                    'last_error' => $controller->last_error,
                ])
                ->values()
                ->all(),
            'last_access_sync_at' => $lastSyncedController?->last_sync_at?->toDateTimeString(),
            'last_access_sync_status' => $controllers->contains('last_sync_status', 'failed')
                ? 'failed'
                : $lastSyncedController?->last_sync_status,
            'pending_access_syncs' => AccessSyncLog::query()->where('status', 'pending')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personalTraining(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return [
            'active_trainers' => PtTrainer::query()->where('status', RecordStatus::Active->value)->count(),
            'active_clients' => PtMemberPackage::query()
                ->where('status', RecordStatus::Active->value)
                ->whereColumn('used_sessions', '<', 'total_sessions')
                ->distinct('member_id')
                ->count('member_id'),
            'remaining_sessions' => (int) PtMemberPackage::query()
                ->where('status', RecordStatus::Active->value)
                ->selectRaw('COALESCE(SUM(total_sessions - used_sessions), 0) as remaining')
                ->value('remaining'),
            'today_sessions' => PtSession::query()
                ->whereDate('session_date', $today)
                ->where('status', 'completed')
                ->count(),
            'monthly_commission' => PtSession::query()
                ->whereBetween('session_date', [$monthStart, $monthEnd])
                ->where('status', 'completed')
                ->sum('commission_amount'),
        ];
    }

    private function completedSalesQuery()
    {
        return Sale::query()->where('status', SaleStatus::Completed->value);
    }

    private function productSalesQuery()
    {
        return $this->completedSalesQuery()
            ->where('sale_type', SaleType::ProductSale->value)
            ->whereDoesntHave('items', fn ($items) => $this->wherePersonalTrainingSaleItem($items));
    }

    private function personalTrainingSalesQuery()
    {
        return $this->completedSalesQuery()
            ->where(function ($query): void {
                $query->where('sale_type', SaleType::PtSession->value)
                    ->orWhere(function ($query): void {
                        $query->where('sale_type', SaleType::ProductSale->value)
                            ->whereHas('items', fn ($items) => $this->wherePersonalTrainingSaleItem($items));
                    });
            });
    }

    private function personalTrainingProductSalesQuery()
    {
        return $this->completedSalesQuery()
            ->where('sale_type', SaleType::ProductSale->value)
            ->whereHas('items', fn ($items) => $this->wherePersonalTrainingSaleItem($items));
    }

    /**
     * @return array<string, float>
     */
    private function categorizedSalesTotals(Carbon $start, Carbon $end): array
    {
        $totalsByType = $this->salesTotalsByType($start, $end);
        $ptProductSales = (float) $this->personalTrainingProductSalesQuery()
            ->whereBetween('completed_at', [$start, $end])
            ->sum('total');

        $productSales = max(0, (float) ($totalsByType[SaleType::ProductSale->value] ?? 0) - $ptProductSales);
        $ptSales = (float) ($totalsByType[SaleType::PtSession->value] ?? 0) + $ptProductSales;
        $membershipSales = collect($this->membershipSaleTypes())
            ->sum(fn (string $type): float => (float) ($totalsByType[$type] ?? 0));

        return [
            'total' => array_sum(array_map('floatval', $totalsByType)),
            'membership' => (float) $membershipSales,
            'product' => $productSales,
            'pt' => $ptSales,
            'walk_in' => (float) ($totalsByType[SaleType::WalkInSale->value] ?? 0),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function salesTotalsByType(Carbon $start, Carbon $end): array
    {
        return $this->completedSalesQuery()
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('sale_type, COALESCE(SUM(total), 0) as total')
            ->groupBy('sale_type')
            ->pluck('total', 'sale_type')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function membershipSaleTypes(): array
    {
        return [
            SaleType::MembershipSale->value,
            SaleType::MembershipRenewal->value,
            SaleType::WalkInSale->value,
        ];
    }

    private function wherePersonalTrainingSaleItem($query): void
    {
        $query->where(function ($query): void {
            $query->where('metadata->sku', 'like', 'SRV-PT-%')
                ->orWhere('description', 'like', '%PT Session%');
        });
    }

    private function expiringSoonMembershipQuery()
    {
        return MemberMembership::query()
            ->where('status', MembershipStatus::Active->value)
            ->whereBetween('end_date', [
                now()->toDateString(),
                now()->addDays(app(SystemSettings::class)->integer('expiring_soon_days'))->toDateString(),
            ]);
    }

    private function expiredMembershipQuery()
    {
        return MemberMembership::query()
            ->where(function ($query): void {
                $query->where('status', MembershipStatus::Expired->value)
                    ->orWhere(function ($query): void {
                        $query->where('status', MembershipStatus::Active->value)
                            ->whereDate('end_date', '<', now()->toDateString());
                    });
            });
    }

    private function databaseIsOnline(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function backupPathStatus(): string
    {
        $path = (string) app(SystemSettings::class)->get('backup_path', config('gym.backup.path'));

        if ($path === '') {
            return 'not_configured';
        }

        if (! is_dir($path)) {
            return 'missing';
        }

        return is_writable($path) ? 'writable' : 'not_writable';
    }
}
