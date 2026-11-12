<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '017'.fake()->numerify('## ######'),
            'role' => Role::Student,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => Role::Staff]);
    }

    /**
     * A student with a profile row.
     */
    public function student(): static
    {
        return $this->state(fn () => ['role' => Role::Student])
            ->afterCreating(fn (User $user) => $user->profile()->create([
                'student_id' => fake()->unique()->numerify('2##-15-####'),
                'department' => 'Computer Science and Engineering',
                'year_of_study' => 2,
            ]));
    }
}
