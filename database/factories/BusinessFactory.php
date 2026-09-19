<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state: a business in its 14-day trial.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-10 days', 'now');

        return [
            'user_id' => User::factory()->state(['role' => 'admin']),
            'business_name' => fake()->randomElement(['Kape', 'Burger', 'Tea', 'Sisig', 'Pandesal', 'Silog']).' '.fake()->randomElement(['Haven', 'Station', 'Corner', 'Republic', 'Hub', 'ni Aling '.fake()->firstName()]),
            'business_type' => fake()->randomElement([
                'Café / coffee shop',
                'Burger & fast food',
                'Milk tea & drinks',
                'Carinderia / eatery',
                'Bakery',
                'Other food business',
            ]),
            'plan' => 'negosyo',
            'status' => BusinessStatus::Trial,
            'start_date' => $startDate,
            'due_date' => (clone $startDate)->modify('+14 days'),
        ];
    }

    /**
     * Link the owner to the business they own, like sign-up does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->owner()->whereNull('business_id')->update(['business_id' => $business->id]);
        });
    }

    /**
     * The cheaper plan: no expenses, no P&L, no recipes, 3 staff.
     */
    public function tindahan(): static
    {
        return $this->state(fn (): array => ['plan' => 'tindahan']);
    }

    /**
     * A paying business, billed monthly.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessStatus::Active,
            'start_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'due_date' => now()->addDays(fake()->numberBetween(1, 28)),
        ]);
    }

    /**
     * A business whose payment or trial is overdue.
     */
    public function pastDue(): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessStatus::PastDue,
            'start_date' => fake()->dateTimeBetween('-3 months', '-1 month'),
            'due_date' => now()->subDays(fake()->numberBetween(1, 10)),
        ]);
    }

    /**
     * A business blocked by the platform operator.
     */
    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessStatus::Suspended,
            'start_date' => fake()->dateTimeBetween('-6 months', '-2 months'),
            'due_date' => now()->subMonth(),
        ]);
    }
}
