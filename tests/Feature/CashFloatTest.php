<?php

use App\Models\CashFloat;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
});

it('computes what should be in the drawer from starting cash, cash sales and expenses', function () {
    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 2000])
        ->assertRedirect(route('admin.day', ['section' => 'sales']));

    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]], 'cash'))->assertCreated();
    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['tea16'], 1]], 'gcash'))->assertCreated();

    $this->actingAs($this->owner)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'Ice', 'amount' => 150,
    ])->assertRedirect();

    $response = $this->actingAs($this->owner)->get(route('admin.day', 'sales'))->assertOk();
    $cashFloat = $response->viewData('data')['cashFloat'];

    expect($cashFloat['starting'])->toBe(2000.0)
        ->and($cashFloat['cashSales'])->toBe(109.0)
        ->and($cashFloat['cashOut'])->toBe(150.0)
        ->and($cashFloat['expected'])->toBe(1959.0)
        ->and($cashFloat['counted'])->toBeNull();

    $response->assertSee('Should be in drawer')->assertSee('1,959.00', false);
});

it('shows the variance once the drawer is counted', function () {
    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 1000])->assertRedirect();
    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]], 'cash'))->assertCreated();

    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 1000, 'counted_amount' => 1100])
        ->assertRedirect();

    $response = $this->actingAs($this->owner)->get(route('admin.day', 'sales'))->assertOk();
    $cashFloat = $response->viewData('data')['cashFloat'];

    expect($cashFloat['expected'])->toBe(1109.0)
        ->and($cashFloat['counted'])->toBe(1100.0)
        ->and($cashFloat['variance'])->toBe(-9.0);

    $response->assertSee('short by')->assertSee('9.00', false);
});

it('says the count matches exactly when there is no variance', function () {
    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 500, 'counted_amount' => 500])
        ->assertRedirect();

    $this->actingAs($this->owner)->get(route('admin.day', 'sales'))->assertOk()->assertSee('matches exactly');
});

it('records who set the starting cash and who counted it', function () {
    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 500])->assertRedirect();

    $float = CashFloat::withoutGlobalScopes()->sole();
    expect($float->started_by)->toBe($this->owner->id)
        ->and($float->started_by_name)->toBe($this->owner->name)
        ->and($float->counted_by)->toBeNull();

    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 500, 'counted_amount' => 480])->assertRedirect();

    expect($float->refresh()->counted_by)->toBe($this->owner->id)
        ->and($float->counted_by_name)->toBe($this->owner->name)
        ->and($float->counted_at)->not->toBeNull();
});

it('shows the cashier the whole shop\'s expected drawer total, not just their own sales', function () {
    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 500])->assertRedirect();

    $otherCashier = cashierOf($this->owner);
    $this->actingAs($otherCashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]], 'cash'))->assertCreated();
    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['tea16'], 1]], 'cash'))->assertCreated();

    // 500 starting + 109 (other cashier) + 45 (this cashier) = 654, shop-wide.
    $this->actingAs($this->cashier)->get(route('staff.orders'))
        ->assertOk()
        ->assertSee('Should be in the drawer')
        ->assertSee('654.00', false);
});

it('only lets the owner set the starting cash, not a cashier', function () {
    $this->actingAs($this->cashier)->post(route('admin.cash-float.store'), ['starting_amount' => 500])
        ->assertRedirect(route('pos'));

    expect(CashFloat::withoutGlobalScopes()->count())->toBe(0);
});

it('cannot see or affect another shop\'s cash drawer', function () {
    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 500])->assertRedirect();

    $stranger = shopOwner();
    $data = $this->actingAs($stranger)->get(route('admin.day', 'sales'))->assertOk()->viewData('data');

    expect($data['cashFloat']['starting'])->toBe(0.0)
        ->and(CashFloat::withoutGlobalScopes()->where('business_id', $stranger->business_id)->count())->toBe(0);
});

it('clears today\'s cash drawer when the day is reset', function () {
    $this->actingAs($this->owner)->post(route('admin.cash-float.store'), ['starting_amount' => 500, 'counted_amount' => 500])->assertRedirect();

    $this->actingAs($this->owner)->post(route('admin.reset-today'), ['confirmation' => $this->business->business_name])
        ->assertRedirect(route('admin.dashboard'));

    expect(CashFloat::withoutGlobalScopes()->count())->toBe(0);
});
