<?php

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Order;
use App\Reports\DailyLedger;
use App\Reports\PeriodReport;
use App\Reports\TrendChart;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);

    $this->sell = fn (array $lines, string $method = 'cash') => $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload($lines, $method))->assertCreated();
    $this->report = fn (?CarbonImmutable $from = null) => app(PeriodReport::class)->build($this->business, $from ?? today()->toImmutable(), today()->toImmutable());
});

it('downloads the report as a PDF from the Profit & ledger page', function () {
    ($this->sell)([[$this->menu['burgerRegular'], 2]]);

    $this->actingAs($this->owner)->get(route('admin.reports'))->assertOk()->assertSee(route('admin.reports.pdf', ['period' => 'custom', 'from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()]));

    $response = $this->actingAs($this->owner)
        ->get(route('admin.reports.pdf', ['period' => 'custom', 'from' => today()->toDateString(), 'to' => today()->toDateString()]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toContain('report-'.today()->toDateString().'-to-'.today()->toDateString().'.pdf')
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

it('reports the same profit as the ledger, with every breakdown adding up', function () {
    ($this->sell)([[$this->menu['burgerRegular'], 2]], 'cash');
    ($this->sell)([[$this->menu['tea22'], 1]], 'gcash');
    ($this->sell)([[$this->menu['water500'], 1]], 'cash');
    $this->actingAs($this->owner)->post(route('pos.orders.void', Order::withoutGlobalScopes()->latest('id')->first()))->assertRedirect();
    Expense::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'date' => today(), 'category' => ExpenseCategory::StockPurchase,
        'kind' => ExpenseCategory::StockPurchase->defaultKind(), 'description' => 'Buns', 'amount' => 500, 'logged_by' => 'Owner',
    ]);

    $report = ($this->report)();
    $ledger = app(DailyLedger::class)->totals(app(DailyLedger::class)->forRange($this->business, today(), today()));

    expect($report['totals'])->toBe($ledger)
        ->and($report['totals']['sales'])->toBe(278.0)
        ->and($report['totals']['stock_purchases'])->toBe(500.0)
        ->and($report['payments']->sum('amount'))->toBe(278.0)
        ->and($report['payments']->firstWhere('label', 'Cash')['orders'])->toBe(1)
        ->and($report['menu']->sum('sales'))->toBe(278.0)
        ->and($report['menu']->sum('cost'))->toBe($ledger['cogs'])
        ->and($report['hours']->sum('amount'))->toBe(278.0)
        ->and($report['weekdays']->sum('amount'))->toBe(278.0)
        ->and($report['cashiers']->sole()['voided'])->toBe(1)
        ->and($report['cashiers']->sole()['voided_amount'])->toBe(25.0)
        ->and($report['stockUsed']->firstWhere('name', 'Burger bun')['qty'])->toBe(2.0)
        ->and($report['expenseCategories']->sole()['lowers_profit'])->toBeFalse();
});

it('includes closing counts, deliveries and the stock on the shelf', function () {
    $this->actingAs($this->owner)->postJson(route('audit.store'), ['counts' => [['item_id' => $this->menu['oil']->id, 'counted' => 4.5]]])->assertOk();

    $report = ($this->report)();

    expect($report['closingCounts']->sole()['cost'])->toBe(72.5)
        ->and($report['closingCounts']->sum('cost'))->toBe($report['totals']['bulk'])
        ->and($report['stock']['rows']->firstWhere('name', 'Burger bun')['value'])->toBe(750.0)
        ->and($report['auditedDays'])->toBe(1);
});

it('charts long periods by week and renders them', function () {
    $this->travel(-60)->days();
    ($this->sell)([[$this->menu['burgerRegular'], 1]]);
    $this->travelBack();
    ($this->sell)([[$this->menu['burgerRegular'], 1]]);

    $this->actingAs($this->owner)
        ->get(route('admin.reports.pdf', ['period' => 'custom', 'from' => today()->subDays(90)->toDateString(), 'to' => today()->toDateString()]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('renders for a shop with no sales at all', function () {
    $this->actingAs(shopOwner())->get(route('admin.reports.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('draws loss days below the zero line', function () {
    $svg = TrendChart::svg(collect([
        ['label' => '1', 'sales' => 1000.0, 'net' => 400.0],
        ['label' => '2', 'sales' => 800.0, 'net' => -600.0],
    ]));

    expect($svg)->toContain('fill="'.TrendChart::LOSS.'"')
        ->and($svg)->toContain('fill="'.TrendChart::PROFIT.'"')
        ->and($svg)->toContain('>-1k<');
});

it('keeps each shop\'s report to itself and follows the reports plan', function () {
    ($this->sell)([[$this->menu['burgerRegular'], 3]]);

    $other = app(PeriodReport::class)->build(shopOwner()->business, today()->toImmutable(), today()->toImmutable());
    expect($other['totals']['sales'])->toBe(0.0)->and($other['menu'])->toBeEmpty();

    $this->actingAs($this->cashier)->get(route('admin.reports.pdf'))->assertRedirect(route('pos'));

    $this->business->update(['plan' => 'tindahan']);
    $this->actingAs($this->owner)->get(route('admin.reports.pdf'))->assertRedirect(route('admin.settings'));
});

it('refuses a future range', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports.pdf', ['period' => 'custom', 'from' => today()->toDateString(), 'to' => today()->addDay()->toDateString()]))
        ->assertSessionHasErrors('to');
});
