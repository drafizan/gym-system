<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategory::factory(),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'selling_price' => fake()->randomFloat(2, 3, 250),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'reorder_level' => fake()->numberBetween(0, 10),
            'status' => RecordStatus::Active->value,
        ];
    }
}
