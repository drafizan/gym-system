<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'ic_passport_no' => fake()->unique()->numerify('######-##-####'),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-16 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_relationship' => fake()->randomElement(['Parent', 'Spouse', 'Sibling', 'Friend']),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'status' => RecordStatus::Active->value,
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
