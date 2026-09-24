<?php

use App\Enums\ExpenseCategory;
use App\Enums\StockMovementReason;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Item;
use App\Models\StockMovement;
use App\Reports\DailyLedger;
use App\Services\Team\TeamService;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
    app(TeamService::class)->updateCashierPermissions($this->business, ['restock_stock' => true]);

    $this->receive = fn (Item $item, float $quantity, array $extra = []) => $this->actingAs($this->cashier)
        ->post(route('staff.products.restock', $item), ['quantity' => $quantity] + $extra)
        ->assertRedirect();
    $this->check = fn (array $data) => $this->actingAs($this->owner)
        ->post(route('admin.deliveries.check', Delivery::withoutGlobalScopes()->latest('id')->first()), $data);
    $this->today = fn () => app(DailyLedger::class)->forRange($this->business, today(), today())->first();
});

it('shows a cashier\'s delivery on the owner\'s dashboard', function () {
    ($this->receive)($this->menu['bun'], 40);

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Deliveries to check')
        ->assertSee('Burger bun · recorded')
        ->assertSee('Received by '.$this->cashier->name);
});

it('settles a matching delivery: the price sets the cost and the purchase is not subtracted from profit', function () {
    ($this->receive)($this->menu['bun'], 40);

    ($this->check)(['receipt_quantity' => 40, 'paid' => 320, 'log_expense' => 1])->assertRedirect();

    $bun = $this->menu['bun']->refresh();
    $expense = Expense::withoutGlobalScopes()->sole();

    expect((float) $bun->on_hand)->toBe(140.0)
        ->and((float) $bun->unit_cost)->toBe(8.0)
        ->and($expense->category)->toBe(ExpenseCategory::StockPurchase)
        ->and((float) $expense->amount)->toBe(320.0)
        ->and(($this->today)()['expenses'])->toBe(0.0)
        ->and(($this->today)()['stock_purchases'])->toBe(320.0)
        ->and(Delivery::withoutGlobalScopes()->sole()->status)->toBe(Delivery::CHECKED);
});

it('logs stock that never reached the shelf as missing, at the receipt price', function () {
    ($this->receive)($this->menu['bun'], 40);

    ($this->check)(['receipt_quantity' => 50, 'paid' => 375, 'log_expense' => 1])
        ->assertSessionHas('status', fn (string $status) => str_contains($status, '10 pc never reached the shelf: ₱75.00 logged as missing stock.'));

    $missing = Expense::withoutGlobalScopes()->where('category', ExpenseCategory::MissingStock)->sole();

    expect((float) $missing->amount)->toBe(75.0)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(140.0)
        ->and((float) $this->menu['bun']->unit_cost)->toBe(7.5)
        ->and(($this->today)()['missing'])->toBe(75.0)
        ->and(($this->today)()['net'])->toBe(-75.0);
});

it('counts a shortage as missing even when the purchase is not logged', function () {
    ($this->receive)($this->menu['bun'], 40);

    ($this->check)(['receipt_quantity' => 50, 'log_expense' => 0])->assertRedirect();

    expect(Expense::withoutGlobalScopes()->pluck('category')->all())->toBe([ExpenseCategory::MissingStock])
        ->and((float) Expense::withoutGlobalScopes()->sole()->amount)->toBe(75.0);
});

it('corrects the count when the cashier recorded more than the receipt', function () {
    ($this->receive)($this->menu['bun'], 40);

    ($this->check)(['receipt_quantity' => 30, 'paid' => 240])->assertRedirect();

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(130.0)
        ->and((float) $this->menu['bun']->unit_cost)->toBe(8.0)
        ->and(StockMovement::withoutGlobalScopes()->where('item_id', $this->menu['bun']->id)->where('reason', StockMovementReason::Adjustment)->sole()->qty_change)->toEqual(-10)
        ->and(Expense::withoutGlobalScopes()->where('category', ExpenseCategory::MissingStock)->exists())->toBeFalse();
});

it('reads the receipt in containers when the cashier used one', function () {
    $ketchup = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'bulk', 'name' => 'Ketchup', 'unit' => 'ml', 'on_hand' => 1000, 'unit_cost' => 0.05]);
    $jug = $ketchup->containers()->create(['label' => 'jug', 'size' => 3000, 'price' => 150, 'sort' => 0]);

    ($this->receive)($ketchup, 1, ['container_id' => $jug->id]);
    ($this->check)(['receipt_quantity' => 2, 'paid' => 360, 'log_expense' => 1])->assertRedirect();

    $ketchup->refresh();
    expect((float) $ketchup->on_hand)->toBe(4000.0)
        ->and((float) $ketchup->unit_cost)->toBe(0.06)
        ->and((float) $jug->refresh()->price)->toBe(180.0)
        ->and((float) Expense::withoutGlobalScopes()->where('category', ExpenseCategory::MissingStock)->sole()->amount)->toBe(180.0);
});

it('logs a supply that is never costed as a normal expense, and no missing stock on top', function () {
    $bags = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'piece', 'name' => 'Paper bag', 'unit' => 'pc', 'on_hand' => 0, 'unit_cost' => 1]);

    ($this->receive)($bags, 90);
    ($this->check)(['receipt_quantity' => 100, 'paid' => 100, 'log_expense' => 1])->assertRedirect();

    expect(Expense::withoutGlobalScopes()->pluck('category')->all())->toBe([ExpenseCategory::Supplies])
        ->and(($this->today)()['expenses'])->toBe(100.0)
        ->and((float) Delivery::withoutGlobalScopes()->sole()->missing_cost)->toBe(0.0);
});

it('checks a delivery only once', function () {
    ($this->receive)($this->menu['bun'], 40);

    ($this->check)(['receipt_quantity' => 50])->assertRedirect();
    ($this->check)(['receipt_quantity' => 50])->assertSessionHasErrors('delivery');

    expect(Expense::withoutGlobalScopes()->count())->toBe(1);
});

it('keeps deliveries inside their shop', function () {
    ($this->receive)($this->menu['bun'], 40);

    $this->actingAs(shopOwner())
        ->post(route('admin.deliveries.check', Delivery::withoutGlobalScopes()->sole()), ['receipt_quantity' => 50])
        ->assertNotFound();
});

it('does not turn an owner\'s own restock into a delivery to check', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $this->menu['bun']), ['quantity' => 10])->assertRedirect();

    expect(Delivery::withoutGlobalScopes()->count())->toBe(0);
});

it('does not take stock off twice when a closing count came before the check', function () {
    $this->business->update(['settings' => ['audit_pieces' => true, 'cashier_permissions' => ['restock_stock' => true]]]);
    ($this->receive)($this->menu['bun'], 40);

    // The shelf really holds 130: the count finds 10 fewer than the 140 the system expects.
    $this->travel(1)->minute();
    $counts = collect([$this->menu['oil'], $this->menu['bun'], $this->menu['patty'], $this->menu['cup16'], $this->menu['cup22']])
        ->map(fn (Item $item) => ['item_id' => $item->id, 'counted' => $item->is($this->menu['bun']) ? 130 : (float) $item->refresh()->on_hand])
        ->all();
    $this->actingAs($this->owner)->postJson(route('audit.store'), ['counts' => $counts])->assertOk();
    expect(($this->today)()['bulk'])->toBe(75.0);

    ($this->check)(['receipt_quantity' => 30, 'paid' => 225])
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'closing count had already corrected the shelf'));

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(130.0)
        ->and(StockMovement::withoutGlobalScopes()->where('reason', StockMovementReason::Adjustment)->exists())->toBeFalse()
        ->and(($this->today)()['bulk'])->toBe(0.0);
});
