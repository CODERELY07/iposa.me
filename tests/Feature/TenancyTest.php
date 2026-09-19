<?php

use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->ownerA = shopOwner(['business_name' => 'Shop A']);
    $this->ownerB = shopOwner(['business_name' => 'Shop B']);
    $this->menuA = demoMenu($this->ownerA);
    $this->menuB = demoMenu($this->ownerB);
});

it('never shows one shop\'s items to another', function () {
    $this->actingAs($this->ownerA)->get(route('admin.inventory.edit', $this->menuB['burger']))->assertNotFound();

    $this->actingAs($this->ownerA);
    expect(Item::count())->toBe(Item::withoutGlobalScopes()->where('business_id', $this->ownerA->business_id)->count());
});

it('never lists another shop\'s orders or expenses', function () {
    $this->actingAs($this->ownerB)->postJson(route('pos.orders.store'), orderPayload([[$this->menuB['burgerRegular'], 1]]));
    Expense::withoutGlobalScopes()->create(['business_id' => $this->ownerB->business_id, 'date' => today(), 'category' => 'rent', 'description' => 'B rent', 'kind' => 'fixed', 'amount' => 5000, 'logged_by' => 'B']);

    $this->actingAs($this->ownerA);
    expect(Order::count())->toBe(0)->and(Expense::count())->toBe(0);

    $this->actingAs($this->ownerA)->get(route('admin.expenses'))->assertDontSee('B rent');
    $this->actingAs($this->ownerA)->get(route('admin.dashboard'))->assertViewHas('today', fn (array $today) => $today['sales'] === 0.0);
});

it('stamps new records with the user\'s own shop', function () {
    $this->actingAs($this->ownerA)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'misc', 'description' => 'Tissue', 'amount' => 50,
    ]);

    expect(Expense::withoutGlobalScopes()->sole()->business_id)->toBe($this->ownerA->business_id);
});

it('lets the platform operator see across shops', function () {
    $operator = User::factory()->superAdmin()->create();

    $this->actingAs($operator);
    expect(Item::count())->toBe(Item::withoutGlobalScopes()->count());
});
