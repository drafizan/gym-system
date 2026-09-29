<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_no' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numberBetween(1, 9999),
            'cashier_id' => User::factory(),
            'sale_type' => SaleType::ProductSale->value,
            'status' => SaleStatus::Completed->value,
            'subtotal' => 10,
            'discount' => 0,
            'total' => 10,
            'completed_at' => now(),
        ];
    }
}
