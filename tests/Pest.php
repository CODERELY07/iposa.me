<?php

use App\Models\Business;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A verified shop owner with their own business (Negosyo plan, in trial).
 */
function shopOwner(array $businessAttributes = []): User
{
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $business = Business::factory()->for($owner, 'owner')->create($businessAttributes);
    $owner->forceFill(['business_id' => $business->id])->save();

    return $owner->refresh();
}

/**
 * A cashier of the owner's business.
 */
function cashierOf(User $owner): User
{
    return User::factory()->staffOf($owner->business)->create();
}

/**
 * A small menu for $owner's shop:
 * - Cheeseburger ₱109 (cost ₱46) uses 1 bun + 1 patty
 * - Iced Tea 16oz ₱45 / 22oz ₱60, each size uses its own cup
 * - Bottled Water ₱25 with no recipe, counted as itself (30 on hand)
 * - Cooking oil: a bulk item (5 bottles at ₱145)
 *
 * @return array<string, Item|ItemVariant>
 */
function demoMenu(User $owner): array
{
    $business = $owner->business;
    $make = fn (array $attributes) => Item::withoutGlobalScopes()->create(['business_id' => $business->id] + $attributes);

    $bun = $make(['kind' => 'piece', 'name' => 'Burger bun', 'unit' => 'pc', 'on_hand' => 100, 'unit_cost' => 7.5, 'low_threshold' => 20]);
    $patty = $make(['kind' => 'piece', 'name' => 'Beef patty', 'unit' => 'pc', 'on_hand' => 100, 'unit_cost' => 24]);
    $cup16 = $make(['kind' => 'piece', 'name' => 'Cups 16oz', 'unit' => 'pc', 'on_hand' => 50, 'unit_cost' => 3.2]);
    $cup22 = $make(['kind' => 'piece', 'name' => 'Cups 22oz', 'unit' => 'pc', 'on_hand' => 50, 'unit_cost' => 4.1]);
    $oil = $make(['kind' => 'bulk', 'name' => 'Cooking oil', 'unit' => '1L bottle', 'on_hand' => 5, 'unit_cost' => 145]);

    $burger = $make(['kind' => 'menu', 'name' => 'Cheeseburger']);
    $burgerRegular = $burger->variants()->create(['label' => 'Regular', 'price' => 109, 'cost' => 46]);
    $burger->recipeLines()->create(['piece_item_id' => $bun->id, 'qty' => 1]);
    $burger->recipeLines()->create(['piece_item_id' => $patty->id, 'qty' => 1]);

    $tea = $make(['kind' => 'menu', 'name' => 'Iced Tea']);
    $tea16 = $tea->variants()->create(['label' => '16oz', 'price' => 45, 'cost' => 9.5, 'sort' => 0]);
    $tea22 = $tea->variants()->create(['label' => '22oz', 'price' => 60, 'cost' => 13, 'sort' => 1]);
    $tea->recipeLines()->create(['piece_item_id' => $cup16->id, 'qty' => 1, 'item_variant_id' => $tea16->id]);
    $tea->recipeLines()->create(['piece_item_id' => $cup22->id, 'qty' => 1, 'item_variant_id' => $tea22->id]);

    $water = $make(['kind' => 'menu', 'name' => 'Bottled Water', 'on_hand' => 30]);
    $water500 = $water->variants()->create(['label' => '500ml', 'price' => 25, 'cost' => 11]);

    return compact('bun', 'patty', 'cup16', 'cup22', 'oil', 'burger', 'burgerRegular', 'tea', 'tea16', 'tea22', 'water', 'water500');
}

/**
 * Checkout payload for the register.
 *
 * @param  list<array{0: ItemVariant, 1: int}>  $lines
 */
function orderPayload(array $lines, string $method = 'cash', ?float $tendered = 1000, ?string $uuid = null): array
{
    return [
        'uuid' => $uuid ?? (string) Str::uuid(),
        'payment_method' => $method,
        'tendered' => $method === 'cash' ? $tendered : null,
        'lines' => array_map(fn (array $line) => ['variant_id' => $line[0]->id, 'qty' => $line[1]], $lines),
    ];
}
