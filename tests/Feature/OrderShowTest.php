<?php

use App\Models\Item;
use App\Models\Order;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);

    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 2]]));
    $this->order = Order::withoutGlobalScopes()->sole();
});

it('shows an order\'s lines and the stock it deducted', function () {
    $this->actingAs($this->owner)->get(route('admin.orders.show', $this->order))
        ->assertOk()
        ->assertSee('Order #'.$this->order->number)
        ->assertSee('Cheeseburger')
        ->assertSee('Burger bun')
        ->assertSee('Beef patty')
        ->assertSee('-2 pc', false);
});

it('shows the void movements alongside the original sale once an order is voided', function () {
    $this->actingAs($this->owner)->post(route('pos.orders.void', $this->order));

    $this->actingAs($this->owner)->get(route('admin.orders.show', $this->order))
        ->assertOk()
        ->assertSee('Voided')
        ->assertSeeInOrder(['Sale', 'Void']);
});

it('says nothing changed stock for an order with no linked or self-counted items', function () {
    $friesItem = Item::withoutGlobalScopes()->create(['business_id' => $this->owner->business_id, 'kind' => 'menu', 'name' => 'Fries']);
    $fries = $friesItem->variants()->create(['label' => 'Regular', 'price' => 60, 'cost' => 20]);

    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$fries, 1]]));
    $order = Order::withoutGlobalScopes()->latest('id')->first();

    $this->actingAs($this->owner)->get(route('admin.orders.show', $order))
        ->assertSee('Fries')
        ->assertSee("didn't change any stock", false);
});

it('cannot view another shop\'s order', function () {
    $stranger = shopOwner();

    $this->actingAs($stranger)->get(route('admin.orders.show', $this->order))->assertNotFound();
});
