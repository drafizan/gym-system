<?php

namespace App\Support;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailySalesReport
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function generate(User $viewer, array $filters = []): array
    {
        $query = $this->baseQuery($viewer, $filters)->with(['cashier', 'member', 'items', 'payments']);
        $sales = $query->get();

        return [
            'filters' => $this->normalizedFilters($viewer, $filters),
            'summary' => $this->summary($sales),
            'payment_breakdown' => $this->paymentBreakdown($sales),
            'transactions' => $this->transactions($sales),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(User $viewer, array $filters): Builder
    {
        $dateFrom = Carbon::parse($filters['date_from'] ?? $filters['date'] ?? now())->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'] ?? $filters['date'] ?? $dateFrom)->endOfDay();

        $query = Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->orderBy('completed_at')
            ->orderBy('receipt_no');

        if (! empty($filters['sale_type'])) {
            $query->where('sale_type', $filters['sale_type']);
        }

        if (! empty($filters['cashier_id']) && $this->canViewAllCashiers($viewer)) {
            $query->where('cashier_id', $filters['cashier_id']);
        }

        if (! empty($filters['payment_method'])) {
            $query->whereHas('payments', function (Builder $query) use ($filters): void {
                $query->where('payment_method', $filters['payment_method']);
            });
        }

        if (! $this->canViewAllCashiers($viewer)) {
            $query->where('cashier_id', $viewer->id);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<string, mixed>
     */
    private function summary(Collection $sales): array
    {
        $membershipSales = round((float) $sales->whereIn('sale_type', [
            SaleType::MembershipSale->value,
            SaleType::MembershipRenewal->value,
            SaleType::WalkInSale->value,
        ])->sum('total'), 2);
        $ptSales = round((float) $sales->filter(fn (Sale $sale): bool => $this->isPersonalTrainingSale($sale))->sum('total'), 2);
        $productSales = round((float) $sales
            ->where('sale_type', SaleType::ProductSale->value)
            ->reject(fn (Sale $sale): bool => $this->isPersonalTrainingSale($sale))
            ->sum('total'), 2);
        $otherSales = round((float) $sales
            ->reject(fn (Sale $sale): bool => in_array($this->salesCategory($sale), ['membership', 'product', 'pt'], true))
            ->sum('total'), 2);

        return [
            'total_revenue' => round((float) $sales->sum('total'), 2),
            'membership_sales' => $membershipSales,
            'product_sales' => $productSales,
            'pt_sales' => $ptSales,
            'walk_in_sales' => round((float) $sales->where('sale_type', SaleType::WalkInSale->value)->sum('total'), 2),
            'other_sales' => $otherSales,
            'discount_total' => round((float) $sales->sum('discount'), 2),
            'transaction_count' => $sales->count(),
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<string, float>
     */
    private function paymentBreakdown(Collection $sales): array
    {
        return $sales
            ->flatMap(fn (Sale $sale) => $sale->payments)
            ->groupBy('payment_method')
            ->map(fn (Collection $payments): float => round((float) $payments->sum('amount'), 2))
            ->sortKeys()
            ->all();
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<int, array<string, mixed>>
     */
    private function transactions(Collection $sales): array
    {
        return $sales->map(function (Sale $sale): array {
            $category = $this->salesCategory($sale);

            return [
                'receipt_no' => $sale->receipt_no,
                'time' => $sale->completed_at?->format('H:i'),
                'type' => $sale->sale_type,
                'type_label' => $category === 'pt' ? 'PT Sales' : str($sale->sale_type)->headline()->toString(),
                'category' => $category,
                'description' => $sale->items->pluck('description')->join(', '),
                'payment_method' => $sale->payments->pluck('payment_method')->join(', '),
                'amount' => (float) $sale->total,
                'received_by' => $sale->cashier?->name,
                'member' => $sale->member?->full_name,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizedFilters(User $viewer, array $filters): array
    {
        return [
            'date_from' => Carbon::parse($filters['date_from'] ?? $filters['date'] ?? now())->toDateString(),
            'date_to' => Carbon::parse($filters['date_to'] ?? $filters['date'] ?? $filters['date_from'] ?? now())->toDateString(),
            'cashier_id' => $this->canViewAllCashiers($viewer) ? ($filters['cashier_id'] ?? null) : $viewer->id,
            'payment_method' => $filters['payment_method'] ?? null,
            'sale_type' => $filters['sale_type'] ?? null,
        ];
    }

    private function canViewAllCashiers(User $viewer): bool
    {
        return in_array($viewer->role?->name, [
            UserRole::Administrator->value,
            UserRole::Manager->value,
        ], true);
    }

    private function salesCategory(Sale $sale): string
    {
        if (str_contains($sale->sale_type, 'membership') || $sale->sale_type === SaleType::WalkInSale->value) {
            return 'membership';
        }

        if ($this->isPersonalTrainingSale($sale)) {
            return 'pt';
        }

        return $sale->sale_type === SaleType::ProductSale->value ? 'product' : 'other';
    }

    private function isPersonalTrainingSale(Sale $sale): bool
    {
        if ($sale->sale_type === SaleType::PtSession->value) {
            return true;
        }

        if ($sale->sale_type !== SaleType::ProductSale->value) {
            return false;
        }

        return $sale->items->contains(function ($item): bool {
            $sku = (string) data_get($item->metadata, 'sku', '');

            return str_starts_with($sku, 'SRV-PT-')
                || str_contains(strtolower((string) $item->description), 'pt session');
        });
    }
}
