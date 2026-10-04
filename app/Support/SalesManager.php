<?php

namespace App\Support;

use App\Enums\AccessSyncAction;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\UserRole;
use App\Models\AccessSyncLog;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPackage;
use App\Models\Product;
use App\Models\PtMemberPackage;
use App\Models\PtPackage;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesManager
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(Request $request, array $data): Sale
    {
        return DB::transaction(function () use ($request, $data): Sale {
            $saleType = SaleType::from($data['sale_type'] ?? SaleType::ProductSale->value);
            $member = isset($data['member_id']) ? Member::query()->lockForUpdate()->findOrFail($data['member_id']) : null;
            if (! empty($data['registration_checkout_token'])) {
                $existing = Sale::query()->where('member_id', $member?->id)
                    ->whereHas('items', fn ($query) => $query->where('metadata->registration_checkout_token', $data['registration_checkout_token']))
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $productItems = $data['product_items'] ?? [];
            $discount = (float) ($data['discount'] ?? 0);

            $sale = Sale::query()->create([
                'receipt_no' => $this->nextReceiptNumber(),
                'member_id' => $member?->id,
                'cashier_id' => $request->user()?->id,
                'sale_type' => $saleType->value,
                'status' => SaleStatus::Completed->value,
                'subtotal' => 0,
                'discount' => $discount,
                'total' => 0,
                'remarks' => $data['remarks'] ?? null,
                'completed_at' => now(),
            ]);

            $subtotal = 0.0;

            if (in_array($saleType, [SaleType::MembershipSale, SaleType::MembershipRenewal, SaleType::WalkInSale], true)) {
                $subtotal += $this->createMembershipSaleItem($request, $sale, $saleType, $member, $data);
            }

            if ($saleType === SaleType::MembershipSale && (float) ($data['registration_fee'] ?? 0) > 0) {
                $fee = (float) $data['registration_fee'];
                $sale->items()->create([
                    'description' => 'Registration Fee',
                    'quantity' => 1,
                    'unit_price' => $fee,
                    'total' => $fee,
                    'metadata' => ['registration_checkout_token' => $data['registration_checkout_token']],
                ]);
                $subtotal += $fee;
            }

            if (! empty($data['pt_package_id'])) {
                $package = PtPackage::query()->findOrFail($data['pt_package_id']);
                if ($package->status !== RecordStatus::Active->value || ! $this->memberHasActiveMembership($member)) {
                    throw ValidationException::withMessages(['member_id' => 'An active member and PT package are required.']);
                }
                $price = (float) $data['pt_price'];
                $purchasedAt = Carbon::parse($data['purchased_at'])->startOfDay();
                $sale->items()->create([
                    'description' => $package->name, 'quantity' => 1, 'unit_price' => $price, 'total' => $price,
                    'metadata' => ['registration_checkout_token' => $data['registration_checkout_token'], 'pt_package_id' => $package->id],
                ]);
                $balance = PtMemberPackage::query()->create([
                    'member_id' => $member->id, 'pt_package_id' => $package->id, 'sale_id' => $sale->id,
                    'total_sessions' => $package->sessions_count, 'used_sessions' => 0, 'price' => round($price - $discount, 2),
                    'purchased_at' => $purchasedAt->toDateString(),
                    'expires_at' => $package->validity_days ? $purchasedAt->copy()->addDays($package->validity_days - 1)->toDateString() : null,
                    'status' => RecordStatus::Active->value, 'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                ]);
                Audit::record($request, 'personal_training', 'member_package_created_from_pos', PtMemberPackage::class, $balance->id, null, $balance->toArray());
                $subtotal += $price;
                $productItems = [];
            }

            foreach ($productItems as $item) {
                $line = $this->createProductSaleItem($request, $sale, $item, $member, $saleType);
                $subtotal += $line['subtotal'];
                $discount += $line['discount'];
            }

            if ($subtotal <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one sale item is required.',
                ]);
            }

            if ($discount < 0 || $discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot exceed sale subtotal.',
                ]);
            }

            $total = round($subtotal - $discount, 2);

            $sale->forceFill([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
            ])->save();

            $paymentMethod = PaymentMethod::from($data['payment_method'] ?? PaymentMethod::Cash->value);
            $allowedPaymentMethods = app(SystemSettings::class)->paymentMethods();

            if (! in_array($paymentMethod->value, $allowedPaymentMethods, true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'This payment method is not enabled.',
                ]);
            }

            $paymentAmount = round((float) ($data['payment_amount'] ?? $total), 2);

            if ($paymentAmount !== $total) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Payment amount must match sale total.',
                ]);
            }

            $sale->payments()->create([
                'payment_method' => $paymentMethod->value,
                'amount' => $paymentAmount,
                'reference_no' => $data['payment_reference'] ?? null,
                'received_by' => $request->user()?->id,
                'paid_at' => now(),
            ]);

            Audit::record($request, 'sales', 'completed', Sale::class, $sale->id, null, $sale->fresh(['items', 'payments'])->toArray());

            return $sale->fresh(['items', 'payments']);
        });
    }

    public function void(Request $request, Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($request, $sale, $reason): Sale {
            $this->ensureCanVoid($request->user());

            $sale->loadMissing('items');

            if ($sale->isVoided()) {
                throw ValidationException::withMessages([
                    'sale' => 'Sale is already voided.',
                ]);
            }

            $oldValues = $sale->toArray();

            foreach ($sale->items as $item) {
                if ($item->product_id) {
                    Product::query()
                        ->whereKey($item->product_id)
                        ->increment('stock_quantity', $item->quantity);
                }
            }

            $sale->forceFill([
                'status' => SaleStatus::Voided->value,
                'voided_at' => now(),
                'voided_by' => $request->user()?->id,
                'void_reason' => $reason,
            ])->save();

            Audit::record($request, 'sales', 'voided', Sale::class, $sale->id, $oldValues, $sale->fresh()->toArray());

            return $sale->fresh(['items', 'payments']);
        });
    }

    private function nextReceiptNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $latest = Sale::query()
            ->where('receipt_no', 'like', $prefix.'%')
            ->orderByDesc('receipt_no')
            ->value('receipt_no');

        $sequence = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createMembershipSaleItem(Request $request, Sale $sale, SaleType $saleType, ?Member $member, array $data): float
    {
        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'A member is required for membership sales.',
            ]);
        }

        $package = MembershipPackage::query()->findOrFail($data['membership_package_id'] ?? null);
        $startDate = Carbon::parse($data['start_date'] ?? now())->startOfDay();
        $endDate = ! empty($data['end_date'])
            ? Carbon::parse($data['end_date'])->startOfDay()
            : MembershipPeriod::endDateFromStart($startDate, $package->duration_days);
        $auditAction = 'assigned';

        if ($saleType === SaleType::MembershipRenewal) {
            $latestMembership = $member->latestMembership()->first();
            $endDate = $latestMembership && $latestMembership->end_date->greaterThanOrEqualTo($startDate)
                ? MembershipPeriod::endDateAfterExistingExpiry($latestMembership->end_date, $package->duration_days)
                : MembershipPeriod::endDateFromStart($startDate, $package->duration_days);
            $auditAction = 'renewed';
        }

        $membership = MemberMembership::query()->create([
            'member_id' => $member->id,
            'membership_package_id' => $package->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => MembershipStatus::Active->value,
            'payment_status' => 'paid',
            'amount' => $data['membership_amount'] ?? $package->price,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        Audit::record($request, 'memberships', $auditAction, MemberMembership::class, $membership->id, null, $membership->toArray());
        $this->queueMembershipAccessSync($membership);

        $amount = (float) ($data['membership_amount'] ?? $package->price);

        $sale->items()->create([
            'membership_package_id' => $package->id,
            'member_membership_id' => $membership->id,
            'description' => $package->name,
            'quantity' => 1,
            'unit_price' => $amount,
            'total' => $amount,
            'metadata' => [
                'registration_checkout_token' => $data['registration_checkout_token'] ?? null,
                'duration_days' => $package->duration_days,
                'start_date' => $membership->start_date->toDateString(),
                'end_date' => $membership->end_date->toDateString(),
            ],
        ]);

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    /**
     * @return array{subtotal: float, discount: float}
     */
    private function createProductSaleItem(Request $request, Sale $sale, array $item, ?Member $member, SaleType $saleType): array
    {
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $product = Product::query()->with('category')->findOrFail($item['product_id'] ?? null);
        $isPersonalTrainingProduct = $this->isPersonalTrainingProduct($product);

        if (! $product->isActive()) {
            throw ValidationException::withMessages([
                'product' => 'Inactive products cannot be sold.',
            ]);
        }

        if ($saleType === SaleType::PtSession && ! $isPersonalTrainingProduct) {
            throw ValidationException::withMessages([
                'product_items' => 'Only personal training packages can be sold under PT Session.',
            ]);
        }

        if ($saleType === SaleType::ProductSale && $isPersonalTrainingProduct) {
            throw ValidationException::withMessages([
                'sale_type' => 'Use PT Session sale type for personal training packages.',
            ]);
        }

        if ($isPersonalTrainingProduct && ! $this->memberHasActiveMembership($member)) {
            throw ValidationException::withMessages([
                'member_id' => 'An active member is required before selling personal training sessions.',
            ]);
        }

        if ($product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'stock' => 'Product stock is not enough for this sale.',
            ]);
        }

        $unitPrice = (float) $product->selling_price;
        $total = round($unitPrice * $quantity, 2);
        $discount = round((float) ($item['discount'] ?? 0), 2);

        if ($discount < 0 || $discount > $unitPrice) {
            throw ValidationException::withMessages([
                'discount' => 'Product line discount cannot exceed the product unit price.',
            ]);
        }

        $product->decrement('stock_quantity', $quantity);

        $sale->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $total,
            'metadata' => [
                'sku' => $product->sku,
                'discount' => $discount,
                'net_total' => round($total - $discount, 2),
            ],
        ]);

        if ($member && $isPersonalTrainingProduct) {
            $this->createPersonalTrainingBalance($request, $sale, $product, $member, $quantity, round($total - $discount, 2));
        }

        return [
            'subtotal' => $total,
            'discount' => $discount,
        ];
    }

    private function isPersonalTrainingProduct(Product $product): bool
    {
        return str_starts_with((string) $product->sku, 'SRV-PT-')
            || (
                strcasecmp((string) $product->category?->name, 'Services') === 0
                && str_contains(strtolower($product->name), 'pt session')
            );
    }

    private function memberHasActiveMembership(?Member $member): bool
    {
        if (! $member || $member->status !== RecordStatus::Active->value) {
            return false;
        }

        $today = now()->toDateString();

        return $member->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();
    }

    private function createPersonalTrainingBalance(Request $request, Sale $sale, Product $product, Member $member, int $quantity, float $netTotal): void
    {
        $package = $this->ptPackageForProduct($product);
        $purchasedAt = now()->startOfDay();

        $memberPackage = PtMemberPackage::query()->create([
            'member_id' => $member->id,
            'pt_package_id' => $package->id,
            'sale_id' => $sale->id,
            'total_sessions' => $package->sessions_count * $quantity,
            'used_sessions' => 0,
            'price' => $netTotal,
            'purchased_at' => $purchasedAt->toDateString(),
            'expires_at' => $package->validity_days ? $purchasedAt->copy()->addDays($package->validity_days - 1)->toDateString() : null,
            'status' => RecordStatus::Active->value,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        Audit::record($request, 'personal_training', 'member_package_created_from_pos', PtMemberPackage::class, $memberPackage->id, null, $memberPackage->toArray());
    }

    private function ptPackageForProduct(Product $product): PtPackage
    {
        $sessionCount = (int) str($product->sku)->after('SRV-PT-')->toString();

        return PtPackage::query()->firstOrCreate([
            'name' => $product->name,
        ], [
            'sessions_count' => max(1, $sessionCount),
            'price' => $product->selling_price,
            'commission_per_session' => 30,
            'status' => RecordStatus::Active->value,
        ]);
    }

    private function queueMembershipAccessSync(MemberMembership $membership): void
    {
        $membership->loadMissing(['member', 'package']);

        AccessSyncLog::query()->create([
            'member_id' => $membership->member_id,
            'member_membership_id' => $membership->id,
            'action' => $membership->package?->access_allowed ? AccessSyncAction::EnableCard->value : AccessSyncAction::DisableCard->value,
            'status' => 'pending',
            'payload' => [
                'member_no' => $membership->member?->member_no,
                'rfid_card_number' => $membership->member?->rfid_card_number,
                'membership_status' => $membership->status,
                'start_date' => $membership->start_date?->toDateString(),
                'end_date' => $membership->end_date?->toDateString(),
            ],
        ]);
    }

    private function ensureCanVoid(?User $user): void
    {
        $role = $user?->role?->name;

        if (! in_array($role, [UserRole::Administrator->value, UserRole::Manager->value], true)) {
            throw ValidationException::withMessages([
                'sale' => 'Only administrators and managers can void sales.',
            ]);
        }
    }
}
