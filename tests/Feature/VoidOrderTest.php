<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Reports\DailyLedger;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);

    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 2]]));
    $this->order = Order::withoutGlobalScopes()->sole();
});

it('sends a void request when the cashier may not void', function () {
    $this->actingAs($this->cashier)->post(route('pos.orders.void', $this->order))->assertRedirect();

    expect($this->order->refresh()->status)->toBe(OrderStatus::VoidRequested)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(98.0);
});

it('lets the owner approve a void request and puts stock back', function () {
    $this->actingAs($this->cashier)->post(route('pos.orders.void', $this->order));

    $this->actingAs($this->owner)->post(route('admin.orders.void.approve', $this->order))->assertRedirect();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Voided)
        ->and($this->order->voided_by)->toBe($this->owner->id)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(100.0)
        ->and((float) $this->menu['patty']->refresh()->on_hand)->toBe(100.0);
});

it('lets the owner reject a void request', function () {
    $this->actingAs($this->cashier)->post(route('pos.orders.void', $this->order));

    $this->actingAs($this->owner)->post(route('admin.orders.void.reject', $this->order));

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('voids directly when the owner allows cashiers to', function () {
    $this->owner->business->update(['settings' => ['cashier_permissions' => ['void_orders' => true]]]);

    $this->actingAs($this->cashier->fresh())->post(route('pos.orders.void', $this->order));

    expect($this->order->refresh()->status)->toBe(OrderStatus::Voided)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(100.0);
});

it('leaves voided orders out of sales', function () {
    $this->actingAs($this->owner)->post(route('pos.orders.void', $this->order));

    $row = app(DailyLedger::class)->forRange($this->owner->business, today(), today())->first();

    expect($row['sales'])->toBe(0.0)->and($row['orders'])->toBe(0);
});

it('cannot void twice', function () {
    $this->actingAs($this->owner)->post(route('pos.orders.void', $this->order));
    $this->actingAs($this->owner)->post(route('admin.orders.void.approve', $this->order))->assertSessionHasErrors('order');

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(100.0);
});

it('cannot touch another shop\'s orders', function () {
    $stranger = shopOwner();

    $this->actingAs($stranger)->post(route('admin.orders.void.approve', $this->order))->assertNotFound();
});
