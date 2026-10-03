<?php

use App\Enums\CostingMethod;
use App\Models\Delivery;
use App\Models\ItemPurchase;
use App\Models\Order;
use App\Models\Supplier;
use App\Reports\PriceHistory;
use App\Services\Team\TeamService;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);

    $this->restock = fn (array $data, $item = null) => $this->actingAs($this->owner)
        ->post(route('admin.inventory.restock', $item ?? $this->menu['bun']), $data)
        ->assertRedirect()->assertSessionDoesntHaveErrors();
});

it('remembers each priced purchase with its supplier and unit cost', function () {
    ($this->restock)(['quantity' => 10, 'paid' => 75, 'supplier' => 'Mang Tonyo Bakery']);

    $purchase = ItemPurchase::withoutGlobalScopes()->sole();

    expect((float) $purchase->unit_cost)->toBe(7.5)
        ->and((float) $purchase->quantity)->toBe(10.0)
        ->and((float) $purchase->paid)->toBe(75.0)
        ->and($purchase->source)->toBe(ItemPurchase::RESTOCK)
        ->and($purchase->supplier->name)->toBe('Mang Tonyo Bakery')
        ->and($purchase->user_id)->toBe($this->owner->id);
});

it('records a purchase without a supplier, and none when nothing was paid', function () {
    ($this->restock)(['quantity' => 10, 'paid' => 80]);
    ($this->restock)(['quantity' => 5]);

    expect(ItemPurchase::withoutGlobalScopes()->count())->toBe(1)
        ->and(ItemPurchase::withoutGlobalScopes()->sole()->supplier_id)->toBeNull()
        ->and(Supplier::withoutGlobalScopes()->count())->toBe(0);
});

it('reuses a supplier typed in a different case or spacing', function () {
    ($this->restock)(['quantity' => 10, 'paid' => 75, 'supplier' => 'Mang Tonyo']);
    ($this->restock)(['quantity' => 10, 'paid' => 80, 'supplier' => '  mang   TONYO ']);

    expect(Supplier::withoutGlobalScopes()->count())->toBe(1)
        ->and(ItemPurchase::withoutGlobalScopes()->pluck('supplier_id')->unique()->count())->toBe(1);
});

it('uses the date the stock was bought, not the day it was entered', function () {
    ($this->restock)(['quantity' => 10, 'paid' => 75, 'date' => today()->subDays(9)->toDateString()]);

    expect(ItemPurchase::withoutGlobalScopes()->sole()->bought_on->toDateString())->toBe(today()->subDays(9)->toDateString());
});

it('records the price from a cashier\'s delivery once the owner checks the receipt', function () {
    app(TeamService::class)->updateCashierPermissions($this->business, ['restock_stock' => true]);
    $this->actingAs(cashierOf($this->owner))->post(route('staff.products.restock', $this->menu['bun']), ['quantity' => 40])->assertRedirect();

    expect(ItemPurchase::withoutGlobalScopes()->count())->toBe(0);

    $this->actingAs($this->owner)->post(route('admin.deliveries.check', Delivery::withoutGlobalScopes()->sole()), [
        'receipt_quantity' => 40, 'paid' => 360, 'supplier' => 'Puregold',
    ])->assertRedirect();

    $purchase = ItemPurchase::withoutGlobalScopes()->sole();
    expect((float) $purchase->unit_cost)->toBe(9.0)
        ->and($purchase->source)->toBe(ItemPurchase::DELIVERY)
        ->and($purchase->supplier->name)->toBe('Puregold');
});

it('shows how much a price change moves the menu items that count their links in cost', function () {
    $this->menu['burger']->update(['costing_method' => CostingMethod::ManualPlusLinked]);
    $this->menu['tea']->update(['costing_method' => CostingMethod::ManualOnly]);
    // The tea's cup is linked too, but the tea doesn't count links in its cost.
    ($this->restock)(['quantity' => 10, 'paid' => 75], $this->menu['bun']);
    ($this->restock)(['quantity' => 10, 'paid' => 90], $this->menu['bun']);
    ($this->restock)(['quantity' => 10, 'paid' => 32], $this->menu['cup16']);
    ($this->restock)(['quantity' => 10, 'paid' => 40], $this->menu['cup16']);

    $bun = app(PriceHistory::class)->forItem($this->menu['bun']);
    $cup = app(PriceHistory::class)->forItem($this->menu['cup16']);

    expect($bun['from'])->toBe(7.5)
        ->and($bun['to'])->toBe(9.0)
        ->and($bun['purchases']->first()['change'])->toBe(20.0)
        ->and($bun['affected']->all())->toBe([['name' => 'Cheeseburger', 'size' => null, 'change' => 1.5]])
        ->and($cup['affected'])->toBeEmpty();
});

it('points at the cheapest supplier by latest price', function () {
    ($this->restock)(['quantity' => 10, 'paid' => 90, 'supplier' => 'Puregold']);
    ($this->restock)(['quantity' => 10, 'paid' => 75, 'supplier' => 'Mang Tonyo']);

    $suppliers = app(PriceHistory::class)->forItem($this->menu['bun'])['suppliers'];

    expect($suppliers->pluck('name')->all())->toBe(['Mang Tonyo', 'Puregold'])
        ->and($suppliers->pluck('cheapest')->all())->toBe([true, false]);
});

it('lists recent price rises on the inventory page, and ignores small or old ones', function () {
    $this->menu['burger']->update(['costing_method' => CostingMethod::ManualPlusLinked]);
    ($this->restock)(['quantity' => 10, 'paid' => 75], $this->menu['bun']);
    ($this->restock)(['quantity' => 10, 'paid' => 90], $this->menu['bun']);
    // +2%: below the 5% line.
    ($this->restock)(['quantity' => 10, 'paid' => 240], $this->menu['patty']);
    ($this->restock)(['quantity' => 10, 'paid' => 245], $this->menu['patty']);
    // A big rise, but long ago.
    ($this->restock)(['quantity' => 10, 'paid' => 32, 'date' => today()->subDays(120)->toDateString()], $this->menu['cup16']);
    ($this->restock)(['quantity' => 10, 'paid' => 64, 'date' => today()->subDays(100)->toDateString()], $this->menu['cup16']);

    $changes = app(PriceHistory::class)->recentIncreases($this->business);

    expect($changes)->toHaveCount(1)
        ->and($changes->first()['item']->is($this->menu['bun']))->toBeTrue()
        ->and($changes->first()['percent'])->toBe(20.0)
        ->and($changes->first()['affected'])->toBe(1);

    $this->actingAs($this->owner)->get(route('admin.inventory'))
        ->assertOk()->assertSee('Prices that went up lately')->assertSee('Burger bun');
});

it('shows the price history on the item page only once something was bought', function () {
    $this->actingAs($this->owner)->get(route('admin.inventory.edit', $this->menu['bun']))->assertOk()->assertDontSee('Price history');

    ($this->restock)(['quantity' => 10, 'paid' => 75, 'supplier' => 'Mang Tonyo']);
    ($this->restock)(['quantity' => 10, 'paid' => 90, 'supplier' => 'Mang Tonyo']);

    $this->actingAs($this->owner)->get(route('admin.inventory.edit', $this->menu['bun']))
        ->assertOk()->assertSee('Price history')->assertSee('Mang Tonyo')->assertSee('went up');
});

it('never changes what an earlier sale cost', function () {
    $this->menu['burger']->update(['costing_method' => CostingMethod::ManualPlusLinked]);
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]]))->assertCreated();
    $before = (float) Order::withoutGlobalScopes()->sole()->lines->sole()->unit_cost;

    ($this->restock)(['quantity' => 10, 'paid' => 200], $this->menu['bun']);

    expect((float) Order::withoutGlobalScopes()->sole()->lines->sole()->unit_cost)->toBe($before);
});

it('keeps one shop\'s suppliers and prices away from another', function () {
    ($this->restock)(['quantity' => 10, 'paid' => 75, 'supplier' => 'Mang Tonyo']);

    $other = shopOwner();
    demoMenu($other);

    $this->actingAs($other)->get(route('admin.inventory'))->assertOk()->assertDontSee('Prices that went up lately');
    $this->actingAs($other)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Mang Tonyo');
    expect(app(PriceHistory::class)->recentIncreases($other->business))->toBeEmpty();
});
