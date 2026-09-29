<?php

namespace Database\Factories;

use App\Models\MembershipPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPackage>
 */
class MembershipPackageFactory extends Factory
{
    protected $model = MembershipPackage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'duration_days' => fake()->randomElement([1, 30, 90, 365]),
            'price' => fake()->randomFloat(2, 10, 500),
            'is_walk_in' => false,
            'access_allowed' => true,
            'status' => 'active',
        ];
    }
}
