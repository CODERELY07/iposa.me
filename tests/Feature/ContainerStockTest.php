<?php

use App\Enums\ExpenseCategory;
use App\Enums\StockMovementReason;
use App\Models\AuditLine;
use App\Models\Expense;
use App\Models\Item;
use App\Models\StockMovement;
use App\Reports\RecipeVariance;
use App\Services\Audit\ClosingAuditService;
use Illuminate\Support\Str;

/**
 * Liquids bought in containers, stored in exact ml, counted at closing by
 * "full containers + ¼ ½ ¾", and optionally used in recipes.
 *
 * The shop from the scenario: cooking oil in ₱145 1-litre bottles, banana
 * ketchup in ₱45 3,785 ml jugs.
 */
beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function oilPayload(array $overrides = []): array
{
    return array_merge([
        'kind' => 'bulk',
        'name' => 'Cooking oil',
        'unit' => 'ml',
        'containers' => [['label' => 'bottle', 'size' => 1000, 'price' => 145]],
        'on_hand' => 3000,
        'low_threshold' => 1000,
    ], $overrides);
}

function makeOil($test): Item
{
    $test->actingAs($test->owner)->post(route('admin.inventory.store'), oilPayload())->assertRedirect();

    return Item::withoutGlobalScopes()->where('name', 'Cooking oil')->with('containers')->firstOrFail();
}

it('works out the cost per ml from what the owner paid for one bottle', function () {
    $oil = makeOil($this);

    expect($oil->unit)->toBe('ml')
        ->and((float) $oil->on_hand)->toBe(3000.0)
        ->and((float) $oil->unit_cost)->toBe(0.145)
        ->and($oil->containers)->toHaveCount(1)
        ->and($oil->containers->first()->label)->toBe('bottle')
        ->and((float) $oil->containers->first()->size)->toBe(1000.0)
        ->and($oil->describeQuantity($oil->on_hand))->toBe('3 bottles · 3,000 ml');
});

it('keeps six decimals so cheap liquids never round to zero', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.store'), oilPayload([
        'name' => 'Banana ketchup',
        'containers' => [['label' => 'jug', 'size' => 3785, 'price' => 45]],
        'on_hand' => 3785,
    ]))->assertRedirect();

    $ketchup = Item::withoutGlobalScopes()->where('name', 'Banana ketchup')->firstOrFail();

    // ₱45 / 3,785 ml = ₱0.011889 per ml; two decimals would have stored ₱0.01.
    expect((float) $ketchup->unit_cost)->toBe(0.011889);
});

it('prices the scenario audit to the centavo: half a bottle of oil, a quarter jug of ketchup', function () {
    $oil = makeOil($this);
    $this->actingAs($this->owner)->post(route('admin.inventory.store'), oilPayload([
        'name' => 'Banana ketchup',
        'containers' => [['label' => 'jug', 'size' => 3785, 'price' => 45]],
        'on_hand' => 3785,
    ]));
    $ketchup = Item::withoutGlobalScopes()->where('name', 'Banana ketchup')->firstOrFail();

    // 2 full bottles + ½ open = 2,500 ml. The jug is ¾ full = 2,838.75 ml.
    $audit = app(ClosingAuditService::class)->submit($this->business, $this->owner, [
        $oil->id => 2500,
        $ketchup->id => 2838.75,
    ]);

    $oilLine = $audit->lines->firstWhere('item_id', $oil->id);
    $ketchupLine = $audit->lines->firstWhere('item_id', $ketchup->id);

    expect((float) $oilLine->used)->toBe(500.0)
        ->and(round((float) $oilLine->used * (float) $oilLine->unit_cost, 2))->toBe(72.50)
        ->and(round((float) $ketchupLine->used * (float) $ketchupLine->unit_cost, 2))->toBe(11.25)
        ->and($audit->usageCost())->toBe(83.75);
});

it('sends containers and a unit-sized step to the audit screen', function () {
    makeOil($this);
    Item::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'kind' => 'bulk', 'name' => 'LPG', 'unit' => 'tank', 'on_hand' => 2, 'unit_cost' => 1100,
    ]);

    $response = $this->actingAs($this->owner)->get(route('audit'))->assertOk();
    $items = collect($response->viewData('bulkItems'))->keyBy('name');

    expect($items['Cooking oil']['containers'])->toBe([['label' => 'bottle', 'size' => 1000.0]])
        ->and($items['Cooking oil']['step'])->toBe(50.0)
        ->and($items['LPG']['containers'])->toBe([])
        ->and($items['LPG']['step'])->toBe(0.25);
});

it('adds a second container size and keeps the cost of the latest price', function () {
    $oil = makeOil($this);
    $bottle = $oil->containers->first();

    // Saving the form untouched never changes the cost.
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $oil), oilPayload([
        'containers' => [['id' => $bottle->id, 'label' => 'bottle', 'size' => 1000, 'price' => 145]],
    ]))->assertRedirect();
    expect((float) $oil->refresh()->unit_cost)->toBe(0.145);

    // Adding an 18 L tin at ₱2,340 makes the tin's price the current cost.
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $oil), oilPayload([
        'containers' => [
            ['id' => $bottle->id, 'label' => 'bottle', 'size' => 1000, 'price' => 145],
            ['label' => 'tin', 'size' => 18000, 'price' => 2340],
        ],
    ]))->assertRedirect();

    $oil->refresh()->load('containers');

    expect($oil->containers->pluck('label')->all())->toBe(['bottle', 'tin'])
        ->and((float) $oil->unit_cost)->toBe(0.13);
});

it('restocks a tin: the count goes up, the cost follows, and the purchase is an expense', function () {
    $oil = makeOil($this);
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $oil), oilPayload([
        'containers' => [
            ['id' => $oil->containers->first()->id, 'label' => 'bottle', 'size' => 1000, 'price' => 145],
            ['label' => 'tin', 'size' => 18000, 'price' => null],
        ],
    ]));
    $tin = $oil->refresh()->containers()->where('label', 'tin')->firstOrFail();

    $this->actingAs($this->owner)
        ->post(route('admin.inventory.restock', $oil), ['quantity' => 1, 'container_id' => $tin->id, 'paid' => 2340, 'log_expense' => 1])
        ->assertRedirect(route('admin.inventory.edit', $oil));

    $oil->refresh();

    expect((float) $oil->on_hand)->toBe(21000.0)
        ->and((float) $oil->unit_cost)->toBe(0.13)
        ->and((float) $tin->refresh()->price)->toBe(2340.0);

    $movement = StockMovement::withoutGlobalScopes()->where('item_id', $oil->id)->latest('id')->first();
    expect($movement->reason)->toBe(StockMovementReason::Restock)
        ->and((float) $movement->qty_change)->toBe(18000.0);

    $expense = Expense::withoutGlobalScopes()->where('business_id', $this->business->id)->latest('id')->first();
    expect($expense->category)->toBe(ExpenseCategory::StockPurchase)
        ->and((float) $expense->amount)->toBe(2340.0)
        ->and($expense->description)->toBe('Cooking oil · 1 tin (18,000 ml)');
});

it('restocks without logging an expense when asked not to, and on the plan without expenses', function () {
    $oil = makeOil($this);

    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $oil), ['quantity' => 2, 'container_id' => $oil->containers->first()->id, 'paid' => 290, 'log_expense' => 0]);
    expect((float) $oil->refresh()->on_hand)->toBe(5000.0)
        ->and(Expense::withoutGlobalScopes()->count())->toBe(0);

    $this->business->forceFill(['plan' => 'tindahan'])->save();
    $this->actingAs($this->owner->fresh())->post(route('admin.inventory.restock', $oil), ['quantity' => 1, 'container_id' => $oil->containers->first()->id, 'paid' => 145, 'log_expense' => 1]);
    expect(Expense::withoutGlobalScopes()->count())->toBe(0);
});

it('restocks an item counted in its own unit', function () {
    $buns = Item::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'kind' => 'piece', 'name' => 'Burger buns', 'unit' => 'pc', 'on_hand' => 10, 'unit_cost' => 7.5,
    ]);

    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $buns), ['quantity' => 50, 'paid' => 400])->assertRedirect();

    expect((float) $buns->refresh()->on_hand)->toBe(60.0)
        ->and((float) $buns->unit_cost)->toBe(8.0);
});

it('refuses to restock made-to-order food or another shop\'s item', function () {
    $burger = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Burger']);
    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $burger), ['quantity' => 1])->assertForbidden();

    $other = shopOwner();
    $theirs = Item::withoutGlobalScopes()->create(['business_id' => $other->business_id, 'kind' => 'piece', 'name' => 'Cups', 'unit' => 'pc', 'on_hand' => 1, 'unit_cost' => 1]);
    $this->actingAs($this->owner)->post(route('admin.inventory.restock', $theirs), ['quantity' => 1])->assertNotFound();
});

it('asks for the count again when an item moves from bottles to ml', function () {
    $old = Item::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'kind' => 'bulk', 'name' => 'Old oil', 'unit' => '1L bottle', 'on_hand' => 4.5, 'unit_cost' => 145,
    ]);

    // Without a new count, 4.5 "bottles" would silently become 4.5 ml.
    $this->actingAs($this->owner)
        ->put(route('admin.inventory.update', $old), oilPayload(['name' => 'Old oil', 'on_hand' => null, 'low_threshold' => null]))
        ->assertSessionHasErrors('on_hand');
    expect((float) $old->refresh()->on_hand)->toBe(4.5);

    $this->actingAs($this->owner)
        ->put(route('admin.inventory.update', $old), oilPayload(['name' => 'Old oil', 'on_hand' => 4500]))
        ->assertSessionHasNoErrors();

    expect((float) $old->refresh()->on_hand)->toBe(4500.0)
        ->and((float) $old->unit_cost)->toBe(0.145);
});

it('leaves items without containers exactly as they were', function () {
    $lpg = Item::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'kind' => 'bulk', 'name' => 'LPG', 'unit' => 'tank', 'on_hand' => 2, 'unit_cost' => 1100,
    ]);

    $this->actingAs($this->owner)->put(route('admin.inventory.update', $lpg), [
        'kind' => 'bulk', 'name' => 'LPG', 'unit' => 'tank', 'unit_cost' => 1150, 'on_hand' => 2,
    ])->assertSessionHasNoErrors();

    expect((float) $lpg->refresh()->unit_cost)->toBe(1150.0)
        ->and($lpg->containers()->count())->toBe(0);
});

it('lets a recipe use a liquid, and the sale takes it off the shelf', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.store'), oilPayload([
        'name' => 'Banana ketchup',
        'containers' => [['label' => 'jug', 'size' => 3785, 'price' => 45]],
        'on_hand' => 3785,
    ]));
    $ketchup = Item::withoutGlobalScopes()->where('name', 'Banana ketchup')->firstOrFail();

    $this->actingAs($this->owner)->post(route('admin.inventory.store'), [
        'kind' => 'menu',
        'name' => 'Burger',
        'variants' => [['label' => 'Regular', 'price' => 89, 'cost' => 30]],
        'recipe' => [['piece_item_id' => $ketchup->id, 'qty' => 15]],
    ])->assertSessionHasNoErrors();

    $burger = Item::withoutGlobalScopes()->where('name', 'Burger')->with('variants')->firstOrFail();

    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), [
        'uuid' => (string) Str::uuid(),
        'payment_method' => 'cash',
        'tendered' => 5340,
        'lines' => [['variant_id' => $burger->variants->first()->id, 'qty' => 60]],
    ])->assertCreated();

    // 60 burgers × 15 ml = 900 ml, deducted at the sale.
    expect((float) $ketchup->refresh()->on_hand)->toBe(2885.0);

    // Closing: the jug is about half full. The audit only charges what the recipes don't explain.
    $audit = app(ClosingAuditService::class)->submit($this->business, $this->owner, [$ketchup->id => 1892.5]);
    $line = $audit->lines->first();

    expect((float) $line->expected)->toBe(2885.0)
        ->and((float) $line->used)->toBe(992.5);

    $variance = app(RecipeVariance::class)->latest($this->business);
    expect($variance['rows'])->toHaveCount(1)
        ->and($variance['rows'][0]['recipe'])->toBe(900.0)
        ->and($variance['rows'][0]['extra'])->toBe(992.5)
        ->and($variance['rows'][0]['total'])->toBe(1892.5)
        ->and($variance['rows'][0]['verdict'])->toContain('More than the recipes explain');

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Liquids · recipes vs the count', false);
});

it('records a count above expected as recipes set too high when the counter says so', function () {
    $this->actingAs($this->owner)->post(route('admin.inventory.store'), oilPayload([
        'name' => 'Banana ketchup',
        'containers' => [['label' => 'jug', 'size' => 3785, 'price' => 45]],
        'on_hand' => 2885,
    ]));
    $ketchup = Item::withoutGlobalScopes()->where('name', 'Banana ketchup')->firstOrFail();
    $burger = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Burger']);
    $burger->recipeLines()->create(['piece_item_id' => $ketchup->id, 'qty' => 15]);

    $this->actingAs($this->owner)->postJson(route('audit.store'), [
        'counts' => [['item_id' => $ketchup->id, 'counted' => 3000, 'surplus' => 'recipe']],
    ])->assertOk();

    $line = $ketchup->refresh()->stockMovements()->exists() ? AuditLine::query()->where('item_id', $ketchup->id)->first() : null;

    expect((float) $line->restocked)->toBe(0.0)
        ->and((float) $line->recipe_surplus)->toBe(115.0)
        ->and((float) $line->used)->toBe(0.0)
        ->and((float) $ketchup->on_hand)->toBe(3000.0);
});

it('keeps a count above expected as a restock for liquids that are in no recipe', function () {
    $oil = makeOil($this);

    $this->actingAs($this->owner)->postJson(route('audit.store'), [
        'counts' => [['item_id' => $oil->id, 'counted' => 3500, 'surplus' => 'recipe']],
    ])->assertOk();

    $line = AuditLine::query()->where('item_id', $oil->id)->first();

    expect((float) $line->restocked)->toBe(500.0)
        ->and((float) $line->recipe_surplus)->toBe(0.0);
});
