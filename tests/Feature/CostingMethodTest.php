<?php

use App\Enums\CostingMethod;
use App\Models\Order;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->burger = $this->menu['burger']->load('recipeLines.piece');
    $this->regular = $this->menu['burgerRegular'];
});

it('prices a size from manual cost only, ignoring linked ingredients', function () {
    $this->burger->update(['costing_method' => CostingMethod::ManualOnly]);

    expect($this->burger->refresh()->load('recipeLines.piece')->costPerSale($this->regular))->toBe(46.0);
});

it('prices a size from linked ingredients only, ignoring the manual cost', function () {
    $this->burger->update(['costing_method' => CostingMethod::LinkedOnly]);

    // 1 bun (₱7.5) + 1 patty (₱24) = ₱31.5, the ₱46 manual cost is ignored.
    expect($this->burger->refresh()->load('recipeLines.piece')->costPerSale($this->regular))->toBe(31.5);
});

it('prices a size from manual cost plus linked ingredients', function () {
    $this->burger->update(['costing_method' => CostingMethod::ManualPlusLinked]);

    expect($this->burger->refresh()->load('recipeLines.piece')->costPerSale($this->regular))->toBe(77.5);
});

it('treats a manual-only item with no typed cost as unknown, not free', function () {
    $this->burger->update(['costing_method' => CostingMethod::ManualOnly]);
    $this->regular->update(['cost' => null]);

    expect($this->burger->refresh()->load('recipeLines.piece')->costPerSale($this->regular->refresh()))->toBeNull();
});

it('treats a linked-only item with nothing linked to this size as unknown, not free', function () {
    $water = $this->menu['water']; // no recipe lines at all
    $water->update(['costing_method' => CostingMethod::LinkedOnly]);

    expect($water->refresh()->load('recipeLines.piece')->costPerSale($this->menu['water500']))->toBeNull();
});

it('treats manual-plus-linked as unknown when either half is missing', function () {
    $this->burger->update(['costing_method' => CostingMethod::ManualPlusLinked]);
    $this->regular->update(['cost' => null]);

    expect($this->burger->refresh()->load('recipeLines.piece')->costPerSale($this->regular->refresh()))->toBeNull();

    $water = $this->menu['water'];
    $water->update(['costing_method' => CostingMethod::ManualPlusLinked]);
    expect($water->refresh()->load('recipeLines.piece')->costPerSale($this->menu['water500']))->toBeNull();
});

it('locks an unknown cost as null at checkout, never rounding it to zero', function () {
    $this->burger->update(['costing_method' => CostingMethod::ManualOnly]);
    $this->regular->update(['cost' => null]);

    $this->actingAs($this->owner)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->regular, 1]]))
        ->assertCreated();

    $line = Order::withoutGlobalScopes()->sole()->lines->sole();
    expect($line->unit_cost)->toBeNull();
});

it('keeps inventory deduction independent of the costing method', function () {
    $this->burger->update(['costing_method' => CostingMethod::ManualOnly]);

    $this->actingAs($this->owner)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->regular, 1]]))
        ->assertCreated();

    // The recipe still comes off the shelf even though it isn't counted in cost.
    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(99.0)
        ->and((float) $this->menu['patty']->refresh()->on_hand)->toBe(99.0);
});

it('never changes a sale\'s locked-in cost after the recipe, manual cost, or costing method change later', function () {
    $this->burger->update(['costing_method' => CostingMethod::ManualPlusLinked]);

    $this->actingAs($this->owner)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->regular, 1]]))
        ->assertCreated();

    $line = Order::withoutGlobalScopes()->sole()->lines->sole();
    expect((float) $line->unit_cost)->toBe(77.5);

    // Everything that fed that ₱77.5 changes after the fact.
    $this->menu['bun']->update(['unit_cost' => 999]);
    $this->regular->update(['cost' => 5]);
    $this->burger->update(['costing_method' => CostingMethod::LinkedOnly]);
    $this->burger->recipeLines()->create(['piece_item_id' => $this->menu['cup16']->id, 'qty' => 1]);

    expect((float) $line->refresh()->unit_cost)->toBe(77.5);
});

it('stamps when a manual cost was set, and only when it actually changes', function () {
    $payload = [
        'kind' => 'menu', 'name' => 'Cheeseburger',
        'variants' => [['id' => $this->regular->id, 'label' => 'Regular', 'cost' => 50, 'price' => 109]],
    ];

    $this->actingAs($this->owner)->put(route('admin.inventory.update', $this->burger), $payload)
        ->assertRedirect()->assertSessionDoesntHaveErrors();

    $stampedAt = $this->regular->refresh()->cost_updated_at;
    expect($stampedAt)->not->toBeNull();

    // Saving again with the SAME cost must not refresh the timestamp.
    $this->travel(1)->day();
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $this->burger), $payload)
        ->assertRedirect()->assertSessionDoesntHaveErrors();
    expect($this->regular->refresh()->cost_updated_at->equalTo($stampedAt))->toBeTrue();

    // Actually changing the cost does refresh it.
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $this->burger), [
        'kind' => 'menu', 'name' => 'Cheeseburger',
        'variants' => [['id' => $this->regular->id, 'label' => 'Regular', 'cost' => 55, 'price' => 109]],
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($this->regular->refresh()->cost_updated_at->greaterThan($stampedAt))->toBeTrue();
});

it('offers the bought-how-many cost helper on every size of a menu item', function () {
    $this->actingAs($this->owner)->get(route('admin.inventory.create'))
        ->assertOk()
        ->assertSee('Bought in bulk? Work out the cost')
        ->assertSee('just fills the Cost box');
});

it('shows a not-reviewed nudge on the item edit page for a stale manual cost', function () {
    $this->regular->forceFill(['cost' => 46, 'cost_updated_at' => now()->subDays(120)])->save();

    $this->actingAs($this->owner)->get(route('admin.inventory.edit', $this->burger))
        ->assertOk()
        ->assertSee('not reviewed in a while');
});
