<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Member;
use App\Models\MembershipPackage;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TodaySampleSalesSeeder extends Seeder
{
    public function run(): void
    {
        $today = now();
        $cashier = User::query()->where('username', 'admin')->first() ?? User::query()->firstOrFail();
        $members = Member::query()->orderBy('member_no')->get()->keyBy('member_no');
        $products = Product::query()->where('status', 'active')->get()->keyBy('sku');
        $packages = MembershipPackage::query()->where('status', 'active')->get()->keyBy('name');

        collect([
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9101',
                'completed_at' => $today->copy()->setTime(9, 15),
                'member_no' => 'GMG26060001',
                'sale_type' => SaleType::MembershipSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['package' => 'Monthly Citizen', 'description' => 'Monthly Citizen Membership - Mohamad Drafizan Bin Drahman', 'quantity' => 1, 'unit_price' => 109],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9102',
                'completed_at' => $today->copy()->setTime(10, 45),
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Qr,
                'items' => [
                    ['product' => 'SUP-PROTEIN-WHEY-1KG', 'description' => 'Protein Whey 1kg x 1', 'quantity' => 1, 'unit_price' => 130],
                    ['product' => 'DRK-WATER-600', 'description' => 'Mineral Water 600ml x 3', 'quantity' => 3, 'unit_price' => 1.50],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9103',
                'completed_at' => $today->copy()->setTime(12, 20),
                'member_no' => 'GMG26060002',
                'sale_type' => SaleType::MembershipRenewal,
                'payment_method' => PaymentMethod::DebitCreditCard,
                'items' => [
                    ['package' => 'Monthly Student', 'description' => 'Monthly Student Membership Renewal - Siti Nurhaliza Binti Ahmad', 'quantity' => 1, 'unit_price' => 89],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9104',
                'completed_at' => $today->copy()->setTime(15, 35),
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['product' => 'DRK-GORILLA-TURBO-TIN', 'description' => 'Gorilla Turbo Tin x 4', 'quantity' => 4, 'unit_price' => 4],
                    ['product' => 'DRK-WATER-1500', 'description' => 'Mineral Water 1.5L x 2', 'quantity' => 2, 'unit_price' => 3],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9105',
                'completed_at' => $today->copy()->setTime(18, 10),
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::DebitCreditCard,
                'items' => [
                    ['product' => 'SUP-PROTEIN-BLEND-1KG', 'description' => 'Protein Blend 1kg x 1', 'quantity' => 1, 'unit_price' => 110],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9106',
                'completed_at' => $today->copy()->setTime(13, 5),
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Qr,
                'items' => [
                    ['product' => 'SRV-RENTAL-TOWEL', 'description' => 'Rental Towel x 2', 'quantity' => 2, 'unit_price' => 3],
                    ['product' => 'DRK-WATER-600', 'description' => 'Mineral Water 600ml x 1', 'quantity' => 1, 'unit_price' => 1.50],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9107',
                'completed_at' => $today->copy()->setTime(14, 50),
                'member_no' => 'GMG26060003',
                'sale_type' => SaleType::MembershipRenewal,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['package' => 'Monthly Citizen', 'description' => 'Monthly Citizen Membership Renewal - Ahmad Rizal Bin Hassan', 'quantity' => 1, 'unit_price' => 109],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9108',
                'completed_at' => $today->copy()->setTime(16, 25),
                'member_no' => 'GMG26060005',
                'sale_type' => SaleType::WalkInSale,
                'payment_method' => PaymentMethod::DebitCreditCard,
                'items' => [
                    ['package' => 'Walk-in Citizen', 'description' => 'Walk-in Citizen Access - Lim Wei Jian', 'quantity' => 1, 'unit_price' => 11],
                ],
            ],
            [
                'receipt_no' => 'INV-'.$today->format('Ymd').'-9109',
                'completed_at' => $today->copy()->setTime(19, 35),
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['product' => 'APP-SHIRT', 'description' => 'Shirt x 1', 'quantity' => 1, 'unit_price' => 80],
                    ['product' => 'SUP-CREATINE', 'description' => 'Creatine x 1', 'quantity' => 1, 'unit_price' => 100],
                    ['product' => 'DRK-WATER-600', 'description' => 'Mineral Water 600ml x 2', 'quantity' => 2, 'unit_price' => 1.50],
                ],
            ],
        ])->each(function (array $sample) use ($cashier, $members, $packages, $products): void {
            /** @var Carbon $completedAt */
            $completedAt = $sample['completed_at'];
            $member = isset($sample['member_no']) ? $members->get($sample['member_no']) : null;
            $subtotal = collect($sample['items'])->sum(fn (array $item): float => (float) $item['unit_price'] * (int) $item['quantity']);

            $sale = Sale::query()->updateOrCreate([
                'receipt_no' => $sample['receipt_no'],
            ], [
                'member_id' => $member?->id,
                'cashier_id' => $cashier->id,
                'sale_type' => $sample['sale_type']->value,
                'status' => SaleStatus::Completed->value,
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'remarks' => 'Today sample sale for dashboard and reports.',
                'completed_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);

            $sale->items()->delete();
            $sale->payments()->delete();

            foreach ($sample['items'] as $item) {
                $product = isset($item['product']) ? $products->get($item['product']) : null;
                $package = isset($item['package']) ? $packages->get($item['package']) : null;
                $lineTotal = (float) $item['unit_price'] * (int) $item['quantity'];

                SaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product?->id,
                    'membership_package_id' => $package?->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $lineTotal,
                    'metadata' => [
                        'seeded' => true,
                        'source' => 'today_sample_sales',
                    ],
                    'created_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);
            }

            SalePayment::query()->create([
                'sale_id' => $sale->id,
                'payment_method' => $sample['payment_method']->value,
                'amount' => $subtotal,
                'reference_no' => $sample['payment_method'] === PaymentMethod::Cash ? null : 'SAMPLE-'.$sale->receipt_no,
                'received_by' => $cashier->id,
                'paid_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        });
    }
}
