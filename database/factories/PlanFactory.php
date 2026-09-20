<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'price' => fake()->numberBetween(199, 1999),
            'pitch' => fake()->sentence(6),
            'staff_limit' => fake()->randomElement([3, 5, null]),
            'features' => ['expenses' => true, 'reports' => true, 'recipes' => true],
            'feature_list' => ['Register (POS)', 'Inventory', 'Closing audit'],
            'sort' => fake()->numberBetween(0, 100),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
