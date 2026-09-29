<?php

use App\Models\Order;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
});

it('lists a piece\'s stock movements newest first, with a running balance and a link to the order', function () {
    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 3]]));
    $order = Order::withoutGlobalScopes()->sole();

    $response = $this->actingAs($this->owner)->get(route('admin.inventory.history', $this->menu['bun']))
        ->assertOk()
        ->assertSee('Burger bun')
        ->assertSee('on hand 97', false)
        ->assertSee('Sale')
        ->assertSee('-3 pc', false)
        ->assertSee('Order #'.$order->number);

    expect($response)->not->toBeNull();
});

it('links a bulk item\'s audit movement to the closing audit day', function () {
    $this->actingAs($this->owner)->postJson(route('audit.store'), [
        'started_at' => now()->subMinute()->toIso8601String(),
        'counts' => [['item_id' => $this->menu['oil']->id, 'counted' => 4]],
    ])->assertOk();

    $this->actingAs($this->owner)->get(route('admin.inventory.history', $this->menu['oil']))
        ->assertOk()
        ->assertSee('Closing audit')
        ->assertSee(route('admin.day', ['section' => 'bulk', 'date' => today()->toDateString()]), false);
});

it('shows an empty state for an item with no stock changes', function () {
    $this->actingAs($this->owner)->get(route('admin.inventory.history', $this->menu['patty']))
        ->assertOk()
        ->assertSee('No stock changes recorded yet.');
});

it('cannot view another shop\'s item history', function () {
    $stranger = shopOwner();

    $this->actingAs($stranger)->get(route('admin.inventory.history', $this->menu['bun']))->assertNotFound();
});
