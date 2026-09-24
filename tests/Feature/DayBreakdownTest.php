<?php

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Order;
use App\Reports\DailyLedger;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
    $this->menu['burger']->update(['include_recipe_cost' => true]);

    $this->sell = fn (array $lines, string $method = 'gcash') => $this->actingAs($this->cashier)
        ->postJson(route('pos.orders.store'), orderPayload($lines, $method))->assertCreated();
    $this->ledger = fn () => app(DailyLedger::class)->forRange($this->business, today(), today())->first();
});

it('lists every order of the day with totals that match the Today page', function () {
    ($this->sell)([[$this->menu['burgerRegular'], 2]], 'cash');
    ($this->sell)([[$this->menu['tea22'], 1]]);
    ($this->sell)([[$this->menu['water500'], 1]]);
    $voided = Order::withoutGlobalScopes()->latest('id')->first();
    $this->actingAs($this->owner)->post(route('pos.orders.void', $voided))->assertRedirect();

    $response = $this->actingAs($this->owner)->get(route('admin.day', 'sales'))->assertOk();

    $data = $response->viewData('data');
    expect($data['orders'])->toHaveCount(3)
        ->and($data['count'])->toBe(2)
        ->and($data['total'])->toBe(($this->ledger)()['sales'])
        ->and($data['total'])->toBe(278.0)
        ->and($data['byMethod']['cash']['amount'])->toBe(218.0)
        ->and($data['byMethod']['gcash']['amount'])->toBe(60.0);

    $response->assertSee('#1')->assertSee('Voided')->assertSee($this->cashier->name)->assertSee('Iced Tea · 22oz', false);
});

it('breaks the ingredients down per size sold and shows what sales took off the shelf', function () {
    ($this->sell)([[$this->menu['burgerRegular'], 2]]);
    ($this->sell)([[$this->menu['tea16'], 3]]);

    $response = $this->actingAs($this->owner)->get(route('admin.day', 'ingredients'))->assertOk();
    $data = $response->viewData('data');

    $burger = $data['sold']->firstWhere('name', 'Cheeseburger');
    expect($burger['qty'])->toBe(2)
        ->and($burger['cost_each'])->toBe(77.5)
        ->and($data['total'])->toBe(($this->ledger)()['cogs'])
        ->and($data['total'])->toBe(183.5);

    $bun = $data['taken']->firstWhere('name', 'Burger bun');
    $cup = $data['taken']->firstWhere('name', 'Cups 16oz');
    expect($bun['qty'])->toBe(2.0)->and($bun['uncosted'])->toBe(0.0)
        ->and($cup['qty'])->toBe(3.0)->and($cup['costed'])->toBe(0.0);

    $response->assertSee('Counted in Ingredients')->assertSee('Not added by the app');
});

it('shows the closing count line by line, adding up to the bulk number', function () {
    $this->actingAs($this->owner)->get(route('admin.day', 'bulk'))->assertOk()->assertSee('No closing count for this day yet');

    $this->actingAs($this->owner)->postJson(route('audit.store'), ['counts' => [['item_id' => $this->menu['oil']->id, 'counted' => 4.5]]])->assertOk();

    $data = $this->actingAs($this->owner)->get(route('admin.day', 'bulk'))->assertOk()->assertSee('Cooking oil')->viewData('data');

    expect($data['total'])->toBe(72.5)
        ->and($data['total'])->toBe(($this->ledger)()['bulk']);
});

it('lists the day\'s expenses and keeps stock purchases out of the total', function () {
    foreach ([[ExpenseCategory::Supplies, 120, 'Ice'], [ExpenseCategory::StockPurchase, 900, 'Buns']] as [$category, $amount, $description]) {
        Expense::withoutGlobalScopes()->create([
            'business_id' => $this->business->id, 'date' => today(), 'category' => $category, 'kind' => $category->defaultKind(),
            'description' => $description, 'amount' => $amount, 'logged_by' => 'Owner',
        ]);
    }

    $data = $this->actingAs($this->owner)->get(route('admin.day', 'expenses'))
        ->assertOk()->assertSee('Ice')->assertSee('not in profit')
        ->viewData('data');

    expect($data['total'])->toBe(120.0)
        ->and($data['total'])->toBe(($this->ledger)()['expenses'])
        ->and($data['stockPurchases'])->toBe(900.0);
});

it('opens any past day, never the future, and only for the owner\'s shop', function () {
    $this->travel(-2)->days();
    ($this->sell)([[$this->menu['burgerRegular'], 1]]);
    $this->travelBack();

    $past = today()->subDays(2)->toDateString();
    $this->actingAs($this->owner)->get(route('admin.day', ['section' => 'sales', 'date' => $past]))
        ->assertOk()
        ->assertViewHas('day', fn ($day) => $day->toDateString() === $past)
        ->assertViewHas('data', fn (array $data) => $data['count'] === 1);

    $this->actingAs($this->owner)->get(route('admin.day', ['section' => 'sales', 'date' => today()->addDay()->toDateString()]))
        ->assertViewHas('day', fn ($day) => $day->isToday());
    $this->actingAs($this->owner)->get(route('admin.day', ['section' => 'sales', 'date' => 'garbage']))->assertOk();

    $this->actingAs(shopOwner())->get(route('admin.day', ['section' => 'sales', 'date' => $past]))
        ->assertViewHas('data', fn (array $data) => $data['count'] === 0);
    $this->actingAs($this->cashier)->get(route('admin.day', 'sales'))->assertRedirect(route('pos'));
    $this->actingAs($this->owner)->get('/admin/day/profit')->assertNotFound();
});

it('links each part of today\'s profit to its page', function () {
    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.day', 'sales'))
        ->assertSee(route('admin.day', 'ingredients'))
        ->assertSee(route('admin.day', 'bulk'))
        ->assertSee(route('admin.day', 'expenses'));
});
