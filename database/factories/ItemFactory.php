<?php

namespace Database\Factories;

use App\Enums\ItemKind;
use App\Models\Business;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state: a menu item with one size.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'kind' => ItemKind::Menu,
            'name' => fake()->randomElement(['Cheeseburger', 'Iced Tea', 'Tapsilog', 'Fries', 'Iced Coffee', 'Nuggets', 'Siomai Rice', 'Pancit', 'Halo-halo', 'Calamansi Juice']),
            'on_hand' => null,
        ];
    }

    /**
     * A sellable item with sizes: ['Regular' => [price, cost]].
     *
     * @param  array<string, array{0: float, 1: float}>  $sizes
     */
    public function menu(array $sizes = ['Regular' => [100, 40]]): static
    {
        return $this->state(fn (): array => ['kind' => ItemKind::Menu])
            ->afterCreating(function (Item $item) use ($sizes): void {
                $sort = 0;

                foreach ($sizes as $label => [$price, $cost]) {
                    $item->variants()->create(['label' => $label, 'price' => $price, 'cost' => $cost, 'sort' => $sort++]);
                }
            });
    }

    public function piece(float $onHand = 100, float $unitCost = 5): static
    {
        return $this->state(fn (): array => [
            'kind' => ItemKind::Piece,
            'unit' => 'pc',
            'on_hand' => $onHand,
            'unit_cost' => $unitCost,
        ]);
    }

    public function bulk(float $onHand = 5, float $unitCost = 100): static
    {
        return $this->state(fn (): array => [
            'kind' => ItemKind::Bulk,
            'unit' => '1L bottle',
            'on_hand' => $onHand,
            'unit_cost' => $unitCost,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
