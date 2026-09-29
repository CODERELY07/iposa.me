<?php

use App\Reports\DailyLedger;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    // Bottled water has no recipe and no manual cost once cleared -- genuinely unknown.
    $this->menu['water500']->update(['cost' => null]);
});

it('computes costing coverage as the share of sold revenue with a known cost', function () {
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([
        [$this->menu['burgerRegular'], 1], // ₱109, known cost ₱46
        [$this->menu['water500'], 1], // ₱25, unknown cost
    ]))->assertCreated();

    $ledger = app(DailyLedger::class);
    $totals = $ledger->totals($ledger->forRange($this->owner->business, today(), today()));

    // 109 of 134 revenue has a known cost: 81.3%.
    expect($totals['sales'])->toBe(134.0)
        ->and($totals['cogs'])->toBe(46.0)
        ->and($totals['coverage'])->toBe(81.3)
        ->and($totals['net'])->toBe(88.0);
});

it('reports full coverage and no cogs when nothing has sold', function () {
    $ledger = app(DailyLedger::class);
    $totals = $ledger->totals($ledger->forRange($this->owner->business, today(), today()));

    expect($totals['coverage'])->toBeNull()
        ->and($totals['cogs'])->toBe(0.0);
});

it('warns on the dashboard when today\'s costing coverage is below 100%', function () {
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['water500'], 1]]));

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Costing coverage')
        ->assertSee("don't have a configured cost yet", false);
});

it('shows no coverage warning when every sale today has a known cost', function () {
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]]));

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Costing coverage');
});
