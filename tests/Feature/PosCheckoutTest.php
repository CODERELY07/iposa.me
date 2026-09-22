<?php

use App\Enums\StockMovementReason;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockMovement;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
});

it('rings up a sale and deducts every linked piece', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 2]], 'cash', 500))
        ->assertCreated()
        ->assertJsonPath('order.number', 1)
        ->assertJsonPath('order.total', 218)
        ->assertJsonPath('order.change', 282);

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(98.0)
        ->and((float) $this->menu['patty']->refresh()->on_hand)->toBe(98.0);

    $order = Order::withoutGlobalScopes()->sole();
    expect($order->cashier_name)->toBe($this->cashier->name)
        ->and($order->lines)->toHaveCount(1)
        ->and((float) $order->lines->first()->unit_cost)->toBe(46.0);

    expect(StockMovement::withoutGlobalScopes()->where('order_id', $order->id)->where('reason', StockMovementReason::Sale)->count())->toBe(2);
});

it('uses the size-specific recipe line', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['tea22'], 3]], 'gcash'))
        ->assertCreated();

    expect((float) $this->menu['cup22']->refresh()->on_hand)->toBe(47.0)
        ->and((float) $this->menu['cup16']->refresh()->on_hand)->toBe(50.0);
});

it('deducts an item with no recipe from its own count', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['water500'], 4]], 'maya'))
        ->assertCreated();

    expect((float) $this->menu['water']->refresh()->on_hand)->toBe(26.0);
});

it('never charges twice for the same order uuid', function () {
    $payload = orderPayload([[$this->menu['burgerRegular'], 1]]);

    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), $payload)->assertCreated();
    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), $payload)->assertOk()->assertJsonPath('order.number', 1);

    expect(Order::withoutGlobalScopes()->count())->toBe(1)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(99.0);
});

it('takes prices from the menu, not from the browser', function () {
    $payload = orderPayload([[$this->menu['burgerRegular'], 1]]);
    $payload['lines'][0]['price'] = 1;
    $payload['subtotal'] = 1;

    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), $payload)->assertCreated()->assertJsonPath('order.total', 109);
});

it('needs enough cash to cover the total', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 2]], 'cash', 100))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tendered');

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

it('numbers orders one after another per shop', function () {
    foreach (range(1, 3) as $expected) {
        $this->actingAs($this->cashier)
            ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['water500'], 1]], 'gcash'))
            ->assertJsonPath('order.number', $expected);
    }

    $otherOwner = shopOwner();
    $otherMenu = demoMenu($otherOwner);
    $this->actingAs($otherOwner)
        ->postJson(route('pos.orders.store'), orderPayload([[$otherMenu['water500'], 1]], 'gcash'))
        ->assertJsonPath('order.number', 1);
});

it('refuses menu items from another shop', function () {
    $otherMenu = demoMenu(shopOwner());

    $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$otherMenu['burgerRegular'], 1]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines');
});

it('refuses archived items', function () {
    $this->menu['burger']->update(['archived_at' => now()]);

    $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]]))
        ->assertUnprocessable();
});

it('refuses a payment method the owner turned off', function () {
    $this->owner->business->update(['settings' => ['payment_methods' => ['cash']]]);

    $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['water500'], 1]], 'gcash'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method');
});

it('shows only sellable, active menu items on the register without costs', function () {
    Item::withoutGlobalScopes()->create(['business_id' => $this->owner->business_id, 'kind' => 'menu', 'name' => 'Old Special', 'archived_at' => now()])
        ->variants()->create(['label' => 'Regular', 'price' => 10, 'cost' => 5]);

    $response = $this->actingAs($this->cashier)->get(route('pos'))->assertOk();

    $menu = collect($response->viewData('menu'));
    expect($menu->pluck('name')->all())->toEqualCanonicalizing(['Cheeseburger', 'Iced Tea', 'Bottled Water'])
        ->and(json_encode($menu))->not->toContain('cost');
});

it('prints a receipt for the order', function () {
    $orderId = $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]], 'cash', 200))
        ->json('order.id');

    $this->actingAs($this->cashier)->get(route('pos.orders.receipt', $orderId))
        ->assertOk()
        ->assertSee('Cheeseburger')
        ->assertSee('₱109.00', false)
        ->assertSee('₱91.00', false);
});

it('offers a full screen toggle on the register', function () {
    $this->actingAs($this->cashier)->get(route('pos'))
        ->assertOk()
        ->assertSee('$store.fullscreen.toggle()', false)
        ->assertSee('Exit full screen', false);
});

it('hides the top bar while in full screen', function () {
    $this->actingAs($this->cashier)->get(route('pos'))
        ->assertOk()
        ->assertSee('x-show="! $store.fullscreen.active"', false);
});
