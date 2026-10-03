<?php

use App\Enums\StockMovementReason;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\Team\TeamService;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->cashier = cashierOf($this->owner);

    // Bottled water in two sizes, each with its own count: 500ml (24) and 1L (6).
    $this->water = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Bottled Water']);
    $this->small = $this->water->variants()->create(['label' => '500ml', 'price' => 20, 'cost' => 10, 'on_hand' => 24, 'sort' => 0]);
    $this->large = $this->water->variants()->create(['label' => '1L', 'price' => 35, 'cost' => 18, 'on_hand' => 6, 'sort' => 1]);

    $this->sell = fn (array $lines) => $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload($lines))->assertCreated();
});

it('takes a sale off only the size that sold, and logs it against that size', function () {
    ($this->sell)([[$this->small, 3]]);

    $movement = StockMovement::withoutGlobalScopes()->sole();

    expect((float) $this->small->refresh()->on_hand)->toBe(21.0)
        ->and((float) $this->large->refresh()->on_hand)->toBe(6.0)
        ->and($movement->item_variant_id)->toBe($this->small->id)
        ->and($movement->item_id)->toBe($this->water->id)
        ->and((float) $movement->qty_change)->toBe(-3.0)
        ->and($movement->reason)->toBe(StockMovementReason::Sale);
});

it('puts a voided sale back on the size it came from', function () {
    ($this->sell)([[$this->small, 2], [$this->large, 1]]);
    $order = Order::withoutGlobalScopes()->sole();

    $this->actingAs($this->owner)->post(route('pos.orders.void', $order))->assertRedirect();

    expect((float) $this->small->refresh()->on_hand)->toBe(24.0)
        ->and((float) $this->large->refresh()->on_hand)->toBe(6.0);
});

it('puts per-size stock back when the day is reset', function () {
    ($this->sell)([[$this->small, 5]]);

    $this->actingAs($this->owner)->post(route('admin.reset-today'), ['confirmation' => $this->business->business_name])->assertRedirect();

    expect((float) $this->small->refresh()->on_hand)->toBe(24.0)
        ->and(StockMovement::withoutGlobalScopes()->count())->toBe(0);
});

it('counts a new multi-size item per size from the form', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.store'), [
        'kind' => 'menu', 'name' => 'Iced Tea',
        'variants' => [
            ['label' => '16oz', 'cost' => 9, 'price' => 45, 'on_hand' => 30],
            ['label' => '22oz', 'cost' => 12, 'price' => 60, 'on_hand' => ''],
        ],
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $tea = Item::withoutGlobalScopes()->where('name', 'Iced Tea')->sole();

    expect((float) $tea->variants->firstWhere('label', '16oz')->on_hand)->toBe(30.0)
        ->and($tea->variants->firstWhere('label', '22oz')->on_hand)->toBeNull()
        ->and($tea->on_hand)->toBeNull();
});

it('logs a size count change as an adjustment on that size', function () {
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $this->water), [
        'kind' => 'menu', 'name' => 'Bottled Water',
        'variants' => [
            ['id' => $this->small->id, 'label' => '500ml', 'cost' => 10, 'price' => 20, 'on_hand' => 20],
            ['id' => $this->large->id, 'label' => '1L', 'cost' => 18, 'price' => 35, 'on_hand' => 6],
        ],
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $movement = StockMovement::withoutGlobalScopes()->sole();

    expect((float) $this->small->refresh()->on_hand)->toBe(20.0)
        ->and((float) $this->large->refresh()->on_hand)->toBe(6.0)
        ->and($movement->item_variant_id)->toBe($this->small->id)
        ->and((float) $movement->qty_change)->toBe(-4.0)
        ->and($movement->reason)->toBe(StockMovementReason::Adjustment);
});

it('moves a shared count onto the sizes and logs the shared count out', function () {
    $shared = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Juice', 'on_hand' => 40]);
    $glass = $shared->variants()->create(['label' => 'Glass', 'price' => 30, 'cost' => 12]);
    $pitcher = $shared->variants()->create(['label' => 'Pitcher', 'price' => 90, 'cost' => 35, 'sort' => 1]);

    $this->actingAs($this->owner)->put(route('admin.inventory.update', $shared), [
        'kind' => 'menu', 'name' => 'Juice',
        'variants' => [
            ['id' => $glass->id, 'label' => 'Glass', 'cost' => 12, 'price' => 30, 'on_hand' => 25],
            ['id' => $pitcher->id, 'label' => 'Pitcher', 'cost' => 35, 'price' => 90, 'on_hand' => 15],
        ],
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $shared->refresh();
    $logged = StockMovement::withoutGlobalScopes()->where('item_id', $shared->id)->whereNull('item_variant_id')->sole();

    expect($shared->on_hand)->toBeNull()
        ->and((float) $glass->refresh()->on_hand)->toBe(25.0)
        ->and((float) $logged->qty_change)->toBe(-40.0);
});

it('keeps a shared count untouched when the form sends no counts', function () {
    $shared = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Juice', 'on_hand' => 40]);
    $glass = $shared->variants()->create(['label' => 'Glass', 'price' => 30, 'cost' => 12]);

    $this->actingAs($this->owner)->put(route('admin.inventory.update', $shared), [
        'kind' => 'menu', 'name' => 'Juice',
        'variants' => [['id' => $glass->id, 'label' => 'Glass', 'cost' => 12, 'price' => 30]],
    ])->assertRedirect();

    expect((float) $shared->refresh()->on_hand)->toBe(40.0);
});

it('restocks the chosen size, and asks which size when the item counts per size', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $this->water), ['quantity' => 12])
        ->assertSessionHasErrors('item_variant_id');

    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $this->water), ['quantity' => 12, 'item_variant_id' => $this->large->id])
        ->assertRedirect()->assertSessionDoesntHaveErrors();

    expect((float) $this->large->refresh()->on_hand)->toBe(18.0)
        ->and((float) $this->small->refresh()->on_hand)->toBe(24.0)
        ->and(StockMovement::withoutGlobalScopes()->sole()->reason)->toBe(StockMovementReason::Restock);
});

it('refuses a size that belongs to another item', function () {
    $other = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Soda']);
    $foreign = $other->variants()->create(['label' => 'Can', 'price' => 30, 'cost' => 15, 'on_hand' => 10]);

    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $this->water), ['quantity' => 5, 'item_variant_id' => $foreign->id])
        ->assertSessionHasErrors('item_variant_id');

    expect((float) $foreign->refresh()->on_hand)->toBe(10.0);
});

it('lets a cashier restock a size, and the owner check corrects that size', function () {
    app(TeamService::class)->updateCashierPermissions($this->business, ['restock_stock' => true]);

    $this->actingAs($this->cashier)->post(route('staff.products.restock', $this->water), ['quantity' => 20, 'item_variant_id' => $this->small->id])
        ->assertRedirect();

    $delivery = Delivery::withoutGlobalScopes()->sole();
    expect($delivery->item_variant_id)->toBe($this->small->id)
        ->and((float) $this->small->refresh()->on_hand)->toBe(44.0);

    // The receipt says 15, so 5 of what the cashier counted never arrived.
    $this->actingAs($this->owner)->post(route('admin.deliveries.check', $delivery), ['receipt_quantity' => 15])->assertRedirect();

    expect((float) $this->small->refresh()->on_hand)->toBe(39.0)
        ->and((float) $this->large->refresh()->on_hand)->toBe(6.0);
});

it('shows a separate history for each size', function () {
    ($this->sell)([[$this->small, 3]]);
    ($this->sell)([[$this->large, 1]]);

    $small = $this->actingAs($this->owner)->get(route('admin.inventory.history', ['item' => $this->water, 'size' => $this->small->id]))
        ->assertOk()->viewData('movements');
    $large = $this->actingAs($this->owner)->get(route('admin.inventory.history', ['item' => $this->water, 'size' => $this->large->id]))
        ->assertOk()->viewData('movements');

    expect($small)->toHaveCount(1)
        ->and($small->first()->balance_after)->toBe(21.0)
        ->and($large)->toHaveCount(1)
        ->and($large->first()->balance_after)->toBe(5.0);
});

it('warns about a low size on its own, on the dashboard and the register', function () {
    $this->water->update(['low_threshold' => 8]);

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Bottled Water · 1L')
        ->assertDontSee('Bottled Water · 500ml');

    $menu = $this->actingAs($this->cashier)->get(route('pos'))->viewData('menu');
    $sizes = collect($menu)->firstWhere('name', 'Bottled Water')['variants'];

    expect(collect($sizes)->firstWhere('label', '1L')['stockLeft'])->toBe(6)
        ->and(collect($sizes)->firstWhere('label', '500ml')['stockLeft'])->toBeNull();
});

it('exports a row for every size that counts itself', function () {
    $csv = $this->actingAs($this->owner)->get(route('admin.exports.download', 'stock'))->streamedContent();

    expect($csv)->toContain('Bottled Water · 500ml')
        ->and($csv)->toContain('Bottled Water · 1L');
});
