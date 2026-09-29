<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberMembership>
 */
class MemberMembershipFactory extends Factory
{
    protected $model = MemberMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'member_id' => Member::factory(),
            'membership_package_id' => MembershipPackage::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => (clone $startDate)->modify('+29 days')->format('Y-m-d'),
            'status' => MembershipStatus::Active->value,
            'payment_status' => 'paid',
            'amount' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
