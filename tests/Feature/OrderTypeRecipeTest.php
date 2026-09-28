<?php

use App\Enums\OrderType;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);

    $this->waxPaper = Item::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'kind' => 'piece', 'name' => 'Wax paper', 'unit' => 'pc', 'on_hand' => 50, 'unit_cost' => 1,
    ]);
    $this->plasticBag = Item::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'kind' => 'piece', 'name' => 'Plastic bag', 'unit' => 'pc', 'on_hand' => 50, 'unit_cost' => 2,
    ]);

    $this->menu['burger']->recipeLines()->create(['piece_item_id' => $this->waxPaper->id, 'qty' => 1, 'order_type' => 'dine_in']);
    $this->menu['burger']->recipeLines()->create(['piece_item_id' => $this->plasticBag->id, 'qty' => 1, 'order_type' => 'take_out']);
});

it('deducts only the dine-in packaging on a dine-in sale', function () {
    $this->actingAs($this->owner)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]], 'gcash', orderType: 'dine_in'))
        ->assertCreated();

    expect((float) $this->waxPaper->refresh()->on_hand)->toBe(49.0)
        ->and((float) $this->plasticBag->refresh()->on_hand)->toBe(50.0)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(99.0)
        ->and(Order::withoutGlobalScopes()->sole()->order_type)->toBe(OrderType::DineIn);
});

it('deducts only the take-out packaging on a take-out sale', function () {
    $this->actingAs($this->owner)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]], 'gcash', orderType: 'take_out'))
        ->assertCreated();

    expect((float) $this->waxPaper->refresh()->on_hand)->toBe(50.0)
        ->and((float) $this->plasticBag->refresh()->on_hand)->toBe(49.0)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(99.0);
});

it('treats a sale with no order type as dine-in', function () {
    $this->actingAs($this->owner)
        ->postJson(route('pos.orders.store'), [
            'uuid' => (string) Str::uuid(),
            'payment_method' => 'gcash',
            'lines' => [['variant_id' => $this->menu['burgerRegular']->id, 'qty' => 1]],
        ])
        ->assertCreated();

    expect((float) $this->waxPaper->refresh()->on_hand)->toBe(49.0)
        ->and((float) $this->plasticBag->refresh()->on_hand)->toBe(50.0);
});
