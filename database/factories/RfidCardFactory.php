<?php

namespace Database\Factories;

use App\Enums\RfidCardStatus;
use App\Models\Member;
use App\Models\RfidCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfidCard>
 */
class RfidCardFactory extends Factory
{
    protected $model = RfidCard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'card_number' => fake()->unique()->numerify('RFID-#####'),
            'status' => RfidCardStatus::Active->value,
            'assigned_at' => now(),
        ];
    }
}
