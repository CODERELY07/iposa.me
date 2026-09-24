<?php

use App\Enums\StockMovementReason;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\RecipeChange;
use App\Models\StockMovement;
use App\Services\Team\TeamService;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
    $this->allow = fn (array $permissions) => app(TeamService::class)->updateCashierPermissions($this->owner->business, $permissions);
});

it('keeps the products screen closed until the owner allows it', function () {
    $this->actingAs($this->cashier)->get(route('staff.products'))->assertForbidden();
    $this->actingAs($this->cashier)->post(route('staff.products.restock', $this->menu['bun']), ['quantity' => 10])->assertForbidden();
    $this->actingAs($this->cashier)->get(route('staff.products.links', $this->menu['burger']))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('pos'))->assertDontSee(route('staff.products'));

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(100.0);
});

it('lets a cashier restock the count without touching the cost', function () {
    ($this->allow)(['restock_stock' => true]);

    $this->actingAs($this->cashier)->get(route('staff.products'))
        ->assertOk()
        ->assertSee('Burger bun')
        ->assertDontSee('Edit links')
        ->assertDontSee('₱');

    $this->actingAs($this->cashier)
        ->post(route('staff.products.restock', $this->menu['bun']), ['quantity' => 50, 'paid' => 999, 'log_expense' => 1])
        ->assertRedirect(route('staff.products'));

    $bun = $this->menu['bun']->refresh();
    expect((float) $bun->on_hand)->toBe(150.0)
        ->and((float) $bun->unit_cost)->toBe(7.5)
        ->and(Expense::withoutGlobalScopes()->count())->toBe(0)
        ->and(StockMovement::withoutGlobalScopes()->where('item_id', $bun->id)->where('reason', StockMovementReason::Restock)->sole()->user_id)->toBe($this->cashier->id);

    $delivery = Delivery::withoutGlobalScopes()->sole();
    expect($delivery->status)->toBe(Delivery::PENDING)
        ->and((float) $delivery->added)->toBe(50.0)
        ->and($delivery->received_by)->toBe($this->cashier->name);
});

it('does not restock made-to-order food', function () {
    ($this->allow)(['restock_stock' => true]);

    $this->actingAs($this->cashier)
        ->post(route('staff.products.restock', $this->menu['burger']), ['quantity' => 5])
        ->assertForbidden();
});

it('sends a cashier\'s link change to the owner instead of applying it', function () {
    ($this->allow)(['link_pieces' => true]);
    $burger = $this->menu['burger'];

    $this->actingAs($this->cashier)->get(route('staff.products.links', $burger))
        ->assertOk()
        ->assertSee('Beef patty')
        ->assertSee('Send to owner')
        ->assertDontSee('₱');

    $this->actingAs($this->cashier)->put(route('staff.products.links.update', $burger), [
        'name' => 'Free Burger',
        'include_recipe_cost' => 1,
        'variants' => [['label' => 'Regular', 'price' => 1, 'cost' => 0]],
        'recipe' => [['piece_item_id' => $this->menu['bun']->id, 'qty' => 2]],
    ])->assertRedirect(route('staff.products'));

    $burger->refresh();
    expect($burger->name)->toBe('Cheeseburger')
        ->and($burger->include_recipe_cost)->toBeFalse()
        ->and((float) $this->menu['burgerRegular']->refresh()->price)->toBe(109.0)
        ->and($burger->recipeLines)->toHaveCount(2);

    $change = RecipeChange::withoutGlobalScopes()->sole();
    expect($change->status)->toBe(RecipeChange::PENDING)
        ->and($change->requested_by)->toBe($this->cashier->name)
        ->and($change->summary())->toBe(['Burger bun: 1 → 2 pc', 'Remove 1 pc Beef patty']);

    $this->actingAs($this->cashier)->get(route('staff.products.links', $burger))->assertSee('Waiting for the owner');
    $this->actingAs($this->cashier)->post(route('staff.products.restock', $this->menu['bun']), ['quantity' => 1])->assertForbidden();
});

it('links a piece to one size by its position once the owner approves', function () {
    ($this->allow)(['link_pieces' => true]);

    $this->actingAs($this->cashier)->put(route('staff.products.links.update', $this->menu['tea']), [
        'recipe' => [['piece_item_id' => $this->menu['cup22']->id, 'qty' => 1, 'variant_index' => 1]],
    ])->assertRedirect();

    $this->actingAs($this->owner)->post(route('admin.recipe-changes.approve', RecipeChange::withoutGlobalScopes()->sole()))->assertRedirect();

    expect($this->menu['tea']->recipeLines()->sole()->item_variant_id)->toBe($this->menu['tea22']->id);
});

it('only links pieces and liquids', function () {
    ($this->allow)(['link_pieces' => true]);

    $this->actingAs($this->cashier)->put(route('staff.products.links.update', $this->menu['burger']), [
        'recipe' => [['piece_item_id' => $this->menu['water']->id, 'qty' => 1]],
    ])->assertSessionHasErrors('recipe.0.piece_item_id');
});

it('shows the products link in the cashier menu once allowed', function () {
    ($this->allow)(['restock_stock' => true]);

    $this->actingAs($this->cashier)->get(route('staff.orders'))->assertSee(route('staff.products'));
});
