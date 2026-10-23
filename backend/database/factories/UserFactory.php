<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** A student with a completed profile. */
    public function definition(): array
    {
        return [
            'phone' => (string) fake()->unique()->numberBetween(6000000000, 9999999999),
            'name' => fake()->name(),
            'nickname' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'target_exam' => 'IMU-CET',
            'attempt_year' => now()->year + 1,
            'profile_completed_at' => now(),
        ];
    }

    /** A student who has logged in (so has an email) but not filled the profile yet. */
    public function withoutProfile(): static
    {
        return $this->state(fn () => [
            'name' => null,
            'nickname' => null,
            'target_exam' => null,
            'attempt_year' => null,
            'profile_completed_at' => null,
        ]);
    }
}
