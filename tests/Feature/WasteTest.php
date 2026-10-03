<?php

use App\Enums\StockMovementReason;
use App\Models\AuditLine;
use App\Models\Expense;
use App\Models\Item;
use App\Models\StockMovement;
use App\Reports\DailyLedger;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);
});

it('takes waste off the count with the reason, as a Waste movement', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 6, 'note' => 'Dropped the tray'])
        ->assertRedirect()->assertSessionDoesntHaveErrors();

    $movement = StockMovement::withoutGlobalScopes()->sole();

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(94.0)
        ->and($movement->reason)->toBe(StockMovementReason::Waste)
        ->and((float) $movement->qty_change)->toBe(-6.0)
        ->and($movement->note)->toBe('Dropped the tray')
        ->and($movement->user_id)->toBe($this->owner->id);
});

it('is not an expense and never moves profit', function () {
    $before = app(DailyLedger::class)->forRange($this->business, today(), today())->first();

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['patty']), ['quantity' => 10, 'note' => 'Expired'])->assertRedirect();

    $after = app(DailyLedger::class)->forRange($this->business, today(), today())->first();

    expect(Expense::withoutGlobalScopes()->count())->toBe(0)
        ->and($after['net'])->toBe($before['net'])
        ->and($after['expenses'])->toBe($before['expenses'])
        ->and($after['money_movement'])->toBe($before['money_movement']);
});

it('counts a container the way a restock does', function () {
    $oil = $this->menu['oil'];
    $oil->update(['unit' => 'ml', 'on_hand' => 5000]);
    $bottle = $oil->containers()->create(['label' => 'bottle', 'size' => 1000, 'price' => 145, 'sort' => 0]);

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $oil), ['quantity' => 0.5, 'container_id' => $bottle->id, 'note' => 'Spilled'])
        ->assertRedirect()->assertSessionDoesntHaveErrors();

    expect((float) $oil->refresh()->on_hand)->toBe(4500.0);
});

it('lets the closing audit start from the lower count, so known waste is not a shortage', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['oil']), ['quantity' => 1, 'note' => 'Knocked over'])->assertRedirect();

    $this->actingAs($this->owner)->postJson(route('audit.store'), ['counts' => [['item_id' => $this->menu['oil']->id, 'counted' => 4]]])->assertOk();

    $line = AuditLine::query()->where('item_id', $this->menu['oil']->id)->sole();
    expect((float) $line->expected)->toBe(4.0)->and((float) $line->used)->toBe(0.0);
});

it('asks which size when the item counts per size, and takes waste off that size', function () {
    $water = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Bottled Water']);
    $small = $water->variants()->create(['label' => '500ml', 'price' => 20, 'cost' => 10, 'on_hand' => 24, 'sort' => 0]);
    $large = $water->variants()->create(['label' => '1L', 'price' => 35, 'cost' => 18, 'on_hand' => 6, 'sort' => 1]);

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $water), ['quantity' => 2])->assertSessionHasErrors('item_variant_id');

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $water), ['quantity' => 2, 'item_variant_id' => $large->id, 'note' => 'Leaking'])
        ->assertRedirect()->assertSessionDoesntHaveErrors();

    $movement = StockMovement::withoutGlobalScopes()->sole();
    expect((float) $large->refresh()->on_hand)->toBe(4.0)
        ->and((float) $small->refresh()->on_hand)->toBe(24.0)
        ->and($movement->item_variant_id)->toBe($large->id)
        ->and($movement->reason)->toBe(StockMovementReason::Waste);
});

it('refuses waste for an item that is not counted', function () {
    $piece = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'piece', 'name' => 'Napkins', 'unit' => 'pc', 'unit_cost' => 1]);

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $piece), ['quantity' => 3])->assertSessionHasErrors('quantity');
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['burger']), ['quantity' => 3])->assertForbidden();

    expect(StockMovement::withoutGlobalScopes()->count())->toBe(0);
});

it('needs a quantity and no future date', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['bun']), [])->assertSessionHasErrors('quantity');
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 1, 'date' => today()->addDay()->toDateString()])->assertSessionHasErrors('date');
});

it('shows the reason in the stock history, and the form on the item page', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 2, 'note' => 'Moldy batch'])->assertRedirect();

    $this->actingAs($this->owner)->get(route('admin.inventory.history', $this->menu['bun']))
        ->assertOk()->assertSee('Waste')->assertSee('Moldy batch');
    $this->actingAs($this->owner)->get(route('admin.inventory.edit', $this->menu['bun']))
        ->assertOk()->assertSee('Log waste');
});

it('is for owners only, and stays inside the shop', function () {
    $this->actingAs(cashierOf($this->owner))->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 1])->assertRedirect(route('pos'));
    $this->actingAs(shopOwner())->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 1])->assertNotFound();

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(100.0);
});
