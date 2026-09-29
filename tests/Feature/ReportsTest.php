<?php

use App\Models\Expense;
use App\Reports\DailyLedger;
use App\Services\Audit\ClosingAuditService;
use App\Services\Pos\CheckoutService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->business = $this->owner->business;

    // Yesterday: 3 cheeseburgers (₱327 sales, ₱138 COGS), oil 5 → 4.5 (₱72.50), ₱300 ice.
    $checkout = app(CheckoutService::class);
    foreach ([1, 2] as $qty) {
        $checkout->checkout($this->business, $this->owner, [
            'uuid' => (string) Str::uuid(), 'payment_method' => 'gcash',
            'lines' => [['variant_id' => $this->menu['burgerRegular']->id, 'qty' => $qty]],
        ], today()->subDay()->setTime(12, 0));
    }
    app(ClosingAuditService::class)->submit($this->business, $this->owner, [$this->menu['oil']->id => 4.5], null, today()->subDay(), today()->subDay()->setTime(21, 30));
    Expense::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'date' => today()->subDay(), 'category' => 'supplies', 'description' => 'Ice', 'kind' => 'variable', 'amount' => 300, 'logged_by' => 'Maria']);
});

it('adds every column of the ledger to the centavo', function () {
    $row = app(DailyLedger::class)->forRange($this->business, today()->subDay(), today()->subDay())->first();

    // Net is cash basis: sales − expenses − stock purchases. cogs/bulk are kept
    // for the Ingredients and Bulk pages, but no longer feed Net.
    expect($row)->toMatchArray([
        'orders' => 2,
        'sales' => 327.0,
        'cogs' => 138.0,
        'bulk' => 72.5,
        'audited' => true,
        'expenses' => 300.0,
        'net' => 27.0,
    ]);
});

it('subtracts a restock in full the day it\'s paid for, not as it sells', function () {
    Expense::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'date' => today()->subDay(), 'category' => 'stock_purchase',
        'description' => 'Buns', 'kind' => 'variable', 'amount' => 500, 'logged_by' => 'Maria',
    ]);

    $ledger = app(DailyLedger::class);
    $totals = $ledger->totals($ledger->forRange($this->business, today()->subDay(), today()->subDay()));

    // 327 sales − 300 expenses − 500 restock, paid in full that day.
    expect($totals['net'])->toBe(-473.0);
});

it('fills days without activity with zeros', function () {
    $rows = app(DailyLedger::class)->forRange($this->business, today()->subDays(6), today());

    expect($rows)->toHaveCount(7)
        ->and($rows->first()['sales'])->toBe(0.0)
        ->and($rows->last()['audited'])->toBeFalse();
});

it('ranks best sellers with their margin', function () {
    $sellers = app(DailyLedger::class)->bestSellers($this->business, today()->subDay(), today());

    expect($sellers->first())->toMatchArray(['name' => 'Cheeseburger', 'sold' => 3, 'revenue' => 327.0, 'margin' => 57.8]);
});

it('shows the profit & ledger page for a custom range', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports', ['period' => 'custom', 'from' => today()->subDay()->toDateString(), 'to' => today()->toDateString()]))
        ->assertOk()
        ->assertViewHas('totals', fn (array $totals) => $totals['sales'] === 327.0 && $totals['net'] === 27.0)
        ->assertSee('27.00', false);
});

it('rejects a report range in the future', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports', ['period' => 'custom', 'from' => today()->toDateString(), 'to' => today()->addDay()->toDateString()]))
        ->assertSessionHasErrors('to');
});

it('shows today on the dashboard with the equation', function () {
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['tea22'], 2]], 'gcash'));

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('profitSoFar', 120.0)
        ->assertSee('Closing audit not done');
});

it('exports the ledger as CSV', function () {
    $response = $this->actingAs($this->owner)->get(route('admin.exports.download', ['dataset' => 'ledger', 'from' => today()->subDay()->toDateString(), 'to' => today()->subDay()->toDateString()]));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('Date,Orders,Sales,"Restock costs",Expenses,Net,"Margin %"')
        ->and($csv)->toContain(today()->subDay()->toDateString().',2,327,0,300,27,8.3');
});

it('exports the menu in the same columns the importer reads', function () {
    $csv = $this->actingAs($this->owner)->get(route('admin.exports.download', 'menu'))->streamedContent();

    expect($csv)->toContain('name,category,size,cost,price')->and($csv)->toContain('Cheeseburger,,Regular,46,109');
});

it('downloads everything as one zip', function () {
    $this->actingAs($this->owner)->get(route('admin.exports.download', 'all'))
        ->assertOk()
        ->assertDownload();
});

it('keeps the P&L behind the Negosyo plan', function () {
    $this->business->update(['plan' => 'tindahan']);

    $this->actingAs($this->owner)->get(route('admin.reports'))->assertRedirect(route('admin.settings'));
});
