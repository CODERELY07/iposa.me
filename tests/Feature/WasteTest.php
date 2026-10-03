<?php

use App\Enums\StockMovementReason;
use App\Models\AuditLine;
use App\Models\Expense;
use App\Models\Item;
use App\Models\StockMovement;
use App\Reports\DailyLedger;
use App\Reports\PeriodReport;
use Carbon\CarbonImmutable;

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

it('counts against profit as Waste, at the item cost, but is not an expense or cash out', function () {
    $before = app(DailyLedger::class)->forRange($this->business, today(), today())->first();

    // 10 patties at ₱24 each.
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['patty']), ['quantity' => 10, 'note' => 'Expired'])
        ->assertRedirect()->assertSessionHas('status', fn (string $status) => str_contains($status, '₱240.00 counted against profit as waste'));

    $after = app(DailyLedger::class)->forRange($this->business, today(), today())->first();

    expect(Expense::withoutGlobalScopes()->count())->toBe(0)
        ->and($after['waste'])->toBe(240.0)
        ->and($after['net'])->toBe($before['net'] - 240.0)
        // Not an expense, and no cash left: the other figures don't move.
        ->and($after['expenses'])->toBe($before['expenses'])
        ->and($after['money_movement'])->toBe($before['money_movement'])
        ->and($after['waste_unpriced'])->toBe(0);
});

it('locks the cost in when it is logged, so a later price change cannot rewrite it', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['patty']), ['quantity' => 5])->assertRedirect();
    $this->menu['patty']->update(['unit_cost' => 99]);

    $row = app(DailyLedger::class)->forRange($this->business, today(), today())->first();
    expect($row['waste'])->toBe(120.0)
        ->and((float) StockMovement::withoutGlobalScopes()->sole()->unit_cost)->toBe(24.0);
});

it('values a size at its own cost', function () {
    $water = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Bottled Water']);
    $water->variants()->create(['label' => '500ml', 'price' => 20, 'cost' => 10, 'on_hand' => 24, 'sort' => 0]);
    $large = $water->variants()->create(['label' => '1L', 'price' => 35, 'cost' => 18, 'on_hand' => 6, 'sort' => 1]);

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $water), ['quantity' => 2, 'item_variant_id' => $large->id])->assertRedirect();

    expect(app(DailyLedger::class)->forRange($this->business, today(), today())->first()['waste'])->toBe(36.0);
});

it('never treats an item with no cost as free: the stock goes down and the entry is flagged', function () {
    $napkins = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'piece', 'name' => 'Napkins', 'unit' => 'pc', 'on_hand' => 100]);

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $napkins), ['quantity' => 10])
        ->assertRedirect()->assertSessionHas('status', fn (string $status) => str_contains($status, 'no cost set'));

    $row = app(DailyLedger::class)->forRange($this->business, today(), today())->first();
    expect((float) $napkins->refresh()->on_hand)->toBe(90.0)
        ->and($row['waste'])->toBe(0.0)
        ->and($row['waste_unpriced'])->toBe(1)
        ->and(StockMovement::withoutGlobalScopes()->sole()->unit_cost)->toBeNull();
});

it('undoes today waste: the stock returns and the loss leaves profit', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['patty']), ['quantity' => 10, 'note' => 'Oops'])->assertRedirect();
    $entry = StockMovement::withoutGlobalScopes()->where('reason', StockMovementReason::Waste)->sole();

    $this->actingAs($this->owner)->post(route('admin.inventory.waste.undo', $entry))->assertRedirect()->assertSessionDoesntHaveErrors();

    $undo = StockMovement::withoutGlobalScopes()->latest('id')->first();
    $row = app(DailyLedger::class)->forRange($this->business, today(), today())->first();

    expect((float) $this->menu['patty']->refresh()->on_hand)->toBe(100.0)
        ->and($undo->reverses_id)->toBe($entry->id)
        ->and((float) $undo->qty_change)->toBe(10.0)
        ->and($row['waste'])->toBe(0.0);

    // Only once.
    $this->actingAs($this->owner)->post(route('admin.inventory.waste.undo', $entry))->assertSessionHasErrors('waste');
    expect((float) $this->menu['patty']->refresh()->on_hand)->toBe(100.0);
});

it('does not let an older entry, a non-waste movement, or another shop be undone', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 2, 'date' => today()->subDay()->toDateString()])->assertRedirect();
    $old = StockMovement::withoutGlobalScopes()->where('reason', StockMovementReason::Waste)->sole();

    $this->actingAs($this->owner)->post(route('admin.inventory.waste.undo', $old))->assertSessionHasErrors('waste');

    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $this->menu['bun']), ['quantity' => 5])->assertRedirect();
    $restock = StockMovement::withoutGlobalScopes()->where('reason', StockMovementReason::Restock)->sole();
    $this->actingAs($this->owner)->post(route('admin.inventory.waste.undo', $restock))->assertSessionHasErrors('waste');

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 1])->assertRedirect();
    $today = StockMovement::withoutGlobalScopes()->where('reason', StockMovementReason::Waste)->latest('id')->first();
    $this->actingAs(shopOwner())->post(route('admin.inventory.waste.undo', $today))->assertNotFound();
});

it('lists the day waste and matches the ledger, with an Undo for today entries', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['patty']), ['quantity' => 3, 'note' => 'Fell on the floor'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['bun']), ['quantity' => 4, 'note' => 'Moldy'])->assertRedirect();

    $response = $this->actingAs($this->owner)->get(route('admin.day', 'waste'))
        ->assertOk()->assertSee('Fell on the floor')->assertSee('Moldy')->assertSee('Undo');

    $ledger = app(DailyLedger::class)->forRange($this->business, today(), today())->first();
    expect($response->viewData('data')['total'])->toBe($ledger['waste'])
        ->and($ledger['waste'])->toBe(102.0);
});

it('adds a Waste tile to Today only on days something was written off, and the P&L and PDF show it', function () {
    $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Stock written off today');

    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['patty']), ['quantity' => 10])->assertRedirect();

    $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertOk()->assertSee('Stock written off today')
        ->assertViewHas('profitSoFar', -240.0);
    $this->actingAs($this->owner)->get(route('admin.reports'))->assertOk()->assertSee('Spilled, expired or thrown-away stock');

    $report = app(PeriodReport::class)->build($this->business, CarbonImmutable::today(), CarbonImmutable::today());
    expect($report['totals']['waste'])->toBe(240.0)
        ->and($report['wasteLines']->first())->toMatchArray(['name' => 'Beef patty', 'qty' => 10.0, 'cost' => 240.0]);
    expect(view('reports.pdf', $report)->render())->toContain('Waste');
});

it('is taken off again when the day is reset', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.waste', $this->menu['patty']), ['quantity' => 10])->assertRedirect();

    $this->actingAs($this->owner)->post(route('admin.reset-today'), ['confirmation' => $this->business->business_name])->assertRedirect();

    expect((float) $this->menu['patty']->refresh()->on_hand)->toBe(100.0)
        ->and(app(DailyLedger::class)->forRange($this->business, today(), today())->first()['waste'])->toBe(0.0);
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
