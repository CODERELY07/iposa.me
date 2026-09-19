<?php

namespace Database\Factories;

use App\Models\Business;
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

    /**
     * A shop owner with their own business.
     */
    public function owner(): static
    {
        return $this->state(fn (): array => ['role' => User::ROLE_ADMIN])
            ->afterCreating(function (User $user): void {
                if ($user->business_id === null) {
                    Business::factory()->for($user, 'owner')->create();
                    $user->refresh();
                }
            });
    }

    /**
     * A cashier of the given business.
     */
    public function staffOf(Business $business): static
    {
        return $this->state(fn (): array => ['role' => User::ROLE_STAFF, 'business_id' => $business->id]);
    }

    /**
     * The platform operator.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (): array => ['role' => User::ROLE_SUPER_ADMIN]);
    }
}
