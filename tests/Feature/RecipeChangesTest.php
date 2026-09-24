<?php

use App\Models\RecipeChange;
use App\Services\Team\TeamService;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
    app(TeamService::class)->updateCashierPermissions($this->owner->business, ['link_pieces' => true]);

    $this->askFor = fn (array $recipe) => $this->actingAs($this->cashier)
        ->put(route('staff.products.links.update', $this->menu['burger']), ['recipe' => $recipe]);
});

it('records who changed an item\'s links when the owner saves them', function () {
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $this->menu['burger']), [
        'kind' => 'menu',
        'name' => 'Cheeseburger',
        'variants' => [['id' => $this->menu['burgerRegular']->id, 'label' => 'Regular', 'cost' => 46, 'price' => 109]],
        'recipe' => [['piece_item_id' => $this->menu['bun']->id, 'qty' => 1]],
    ])->assertRedirect();

    $change = RecipeChange::withoutGlobalScopes()->sole();
    expect($change->status)->toBe(RecipeChange::SAVED)
        ->and($change->requested_by)->toBe($this->owner->name)
        ->and($change->summary())->toBe(['Remove 1 pc Beef patty']);

    $this->actingAs($this->owner)->get(route('admin.inventory.edit', $this->menu['burger']))
        ->assertOk()
        ->assertSee('Link history')
        ->assertSee('Remove 1 pc Beef patty');
});

it('records nothing when the owner saves the same links', function () {
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $this->menu['burger']), [
        'kind' => 'menu',
        'name' => 'Cheeseburger Deluxe',
        'variants' => [['id' => $this->menu['burgerRegular']->id, 'label' => 'Regular', 'cost' => 46, 'price' => 120]],
        'recipe' => [['piece_item_id' => $this->menu['patty']->id, 'qty' => 1], ['piece_item_id' => $this->menu['bun']->id, 'qty' => 1]],
    ])->assertRedirect();

    expect(RecipeChange::withoutGlobalScopes()->count())->toBe(0);
});

it('lets the owner approve a cashier\'s request from the dashboard', function () {
    ($this->askFor)([['piece_item_id' => $this->menu['bun']->id, 'qty' => 2], ['piece_item_id' => $this->menu['patty']->id, 'qty' => 1]]);

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Link change requests')
        ->assertSee('Burger bun: 1 → 2 pc');

    $change = RecipeChange::withoutGlobalScopes()->sole();
    $this->actingAs($this->owner)->post(route('admin.recipe-changes.approve', $change))->assertRedirect();

    expect((float) $this->menu['burger']->recipeLines()->where('piece_item_id', $this->menu['bun']->id)->value('qty'))->toBe(2.0)
        ->and($change->refresh()->status)->toBe(RecipeChange::APPROVED)
        ->and($change->decided_by_name)->toBe($this->owner->name);
});

it('leaves the links alone when the owner rejects', function () {
    ($this->askFor)([['piece_item_id' => $this->menu['patty']->id, 'qty' => 1]]);
    $change = RecipeChange::withoutGlobalScopes()->sole();

    $this->actingAs($this->owner)->post(route('admin.recipe-changes.reject', $change))->assertRedirect();

    expect($this->menu['burger']->recipeLines()->count())->toBe(2)
        ->and($change->refresh()->status)->toBe(RecipeChange::REJECTED);

    $this->actingAs($this->owner)->post(route('admin.recipe-changes.approve', $change))->assertSessionHasErrors('change');
    expect($this->menu['burger']->recipeLines()->count())->toBe(2);
});

it('replaces an older request with the newer one', function () {
    ($this->askFor)([['piece_item_id' => $this->menu['patty']->id, 'qty' => 1]]);
    ($this->askFor)([['piece_item_id' => $this->menu['bun']->id, 'qty' => 1]]);

    expect(RecipeChange::withoutGlobalScopes()->orderBy('id')->pluck('status')->all())->toBe([RecipeChange::REPLACED, RecipeChange::PENDING]);
});

it('does not make a request when nothing would change', function () {
    ($this->askFor)([['piece_item_id' => $this->menu['bun']->id, 'qty' => 1], ['piece_item_id' => $this->menu['patty']->id, 'qty' => 1]])
        ->assertSessionHas('status', 'No changes to Cheeseburger.');

    expect(RecipeChange::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses to approve a request made before the links last changed', function () {
    ($this->askFor)([['piece_item_id' => $this->menu['patty']->id, 'qty' => 1]]);
    $this->menu['burger']->recipeLines()->where('piece_item_id', $this->menu['bun']->id)->update(['qty' => 3]);

    $this->actingAs($this->owner)->post(route('admin.recipe-changes.approve', RecipeChange::withoutGlobalScopes()->sole()))
        ->assertSessionHasErrors('change');

    expect((float) $this->menu['burger']->recipeLines()->where('piece_item_id', $this->menu['bun']->id)->value('qty'))->toBe(3.0);
});

it('refuses to approve when a requested piece was archived since', function () {
    ($this->askFor)([['piece_item_id' => $this->menu['cup16']->id, 'qty' => 1]]);
    $this->menu['cup16']->update(['archived_at' => now()]);

    $this->actingAs($this->owner)->post(route('admin.recipe-changes.approve', RecipeChange::withoutGlobalScopes()->sole()))
        ->assertSessionHasErrors('change');

    expect($this->menu['burger']->recipeLines()->count())->toBe(2);
});

it('keeps requests between the shop and its owner', function () {
    ($this->askFor)([['piece_item_id' => $this->menu['patty']->id, 'qty' => 1]]);
    $change = RecipeChange::withoutGlobalScopes()->sole();

    $this->actingAs(shopOwner())->post(route('admin.recipe-changes.approve', $change))->assertNotFound();
    $this->actingAs($this->cashier)->post(route('admin.recipe-changes.approve', $change))->assertRedirect(route('pos'));

    expect($change->refresh()->status)->toBe(RecipeChange::PENDING);
});
