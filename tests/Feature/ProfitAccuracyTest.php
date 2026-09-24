<?php

use App\Enums\ExpenseCategory;
use App\Models\AuditLine;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use App\Models\RecipeChange;
use App\Reports\DailyLedger;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->ketchup = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'bulk', 'name' => 'Ketchup', 'unit' => 'ml', 'on_hand' => 3000, 'unit_cost' => 0.05]);
    $this->ketchup->containers()->create(['label' => 'jug', 'size' => 3000, 'price' => 150, 'sort' => 0]);
    $this->burger = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'menu', 'name' => 'Burger', 'include_recipe_cost' => true]);
    $this->regular = $this->burger->variants()->create(['label' => 'Regular', 'price' => 100, 'cost' => 40]);
    $this->burger->recipeLines()->create(['piece_item_id' => $this->ketchup->id, 'qty' => 15]);

    $this->sell = fn (int $qty) => $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([[$this->regular, $qty]], 'gcash'))->assertCreated();
    $this->count = fn (float $counted, ?string $surplus = null) => $this->actingAs($this->owner)
        ->postJson(route('audit.store'), ['counts' => [['item_id' => $this->ketchup->id, 'counted' => $counted, 'surplus' => $surplus]]])
        ->assertOk();
    $this->line = fn () => AuditLine::query()->where('item_id', $this->ketchup->id)->latest('id')->first();
    $this->today = fn () => app(DailyLedger::class)->forRange($this->business, today(), today())->first();
});

it('costs a bottled liquid per ml when the item includes its links', function () {
    ($this->sell)(1);

    expect((float) Order::withoutGlobalScopes()->sole()->lines->sole()->unit_cost)->toBe(40.75);
});

it('gives back exactly what the recipes over-charged when the count says they use too much', function () {
    ($this->sell)(10);
    ($this->count)(2880, 'recipe');

    $line = ($this->line)();
    $today = ($this->today)();

    expect((float) $line->recipe_surplus)->toBe(30.0)
        ->and((float) $line->recipe_deducted)->toBe(150.0)
        ->and((float) $line->recipe_surplus_costed)->toBe(30.0)
        ->and($today['cogs'])->toBe(407.5)
        ->and($today['bulk'])->toBe(-1.5)
        ->and($today['net'])->toBe(594.0);
});

it('gives nothing back for recipes whose cost the sales never charged', function () {
    $this->burger->update(['include_recipe_cost' => false]);

    ($this->sell)(10);
    ($this->count)(2880, 'recipe');

    expect((float) ($this->line)()->recipe_surplus)->toBe(30.0)
        ->and((float) ($this->line)()->recipe_surplus_costed)->toBe(0.0)
        ->and(($this->today)()['bulk'])->toBe(0.0);
});

it('treats a surplus beyond what the recipes took as a restock', function () {
    ($this->sell)(10);
    ($this->count)(3200, 'recipe');

    expect((float) ($this->line)()->recipe_surplus)->toBe(150.0)
        ->and((float) ($this->line)()->restocked)->toBe(200.0);
});

it('leaves voided sales out of what the recipes took', function () {
    ($this->sell)(6);
    ($this->sell)(4);
    $this->actingAs($this->owner)->post(route('pos.orders.void', Order::withoutGlobalScopes()->latest('id')->first()))->assertRedirect();

    ($this->count)(2910, 'recipe');

    expect((float) ($this->line)()->recipe_deducted)->toBe(90.0)
        ->and((float) ($this->line)()->recipe_deducted_costed)->toBe(90.0)
        ->and((float) ($this->line)()->recipe_surplus)->toBe(0.0);
});

it('only looks at what the recipes took since the previous count', function () {
    ($this->sell)(10);
    ($this->count)(2850);

    $this->travel(1)->day();
    ($this->sell)(2);
    ($this->count)(2830, 'recipe');

    expect((float) ($this->line)()->recipe_deducted)->toBe(30.0)
        ->and((float) ($this->line)()->recipe_surplus)->toBe(10.0);
});

it('keeps the first count\'s window when the owner corrects it', function () {
    ($this->sell)(10);
    ($this->count)(2850);
    ($this->sell)(2);
    ($this->count)(2880, 'recipe');

    expect((float) ($this->line)()->recipe_deducted)->toBe(150.0)
        ->and((float) ($this->line)()->recipe_surplus)->toBe(30.0);
});

it('offers to lower the recipes and records the change', function () {
    ($this->sell)(10);
    ($this->count)(2880, 'recipe');

    $this->actingAs($this->owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Recipes that use too much')
        ->assertSee('80%');

    $this->actingAs($this->owner)->post(route('admin.recipe-fixes.apply', ($this->line)()))
        ->assertSessionHas('status', 'Updated 1 recipe that use Ketchup.');

    expect((float) $this->burger->recipeLines()->sole()->qty)->toBe(12.0)
        ->and(($this->line)()->recipe_fix)->toBe('applied')
        ->and(RecipeChange::withoutGlobalScopes()->sole()->summary())->toBe(['Ketchup: 15 → 12 ml']);

    $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertDontSee('Recipes that use too much');
    $this->actingAs($this->owner)->post(route('admin.recipe-fixes.apply', ($this->line)()))->assertSessionHasErrors('recipe');
});

it('lets the owner keep the recipes as they are', function () {
    ($this->sell)(10);
    ($this->count)(2880, 'recipe');

    $this->actingAs($this->owner)->post(route('admin.recipe-fixes.dismiss', ($this->line)()))->assertRedirect();

    expect((float) $this->burger->recipeLines()->sole()->qty)->toBe(15.0)
        ->and(($this->line)()->recipe_fix)->toBe('dismissed');
});

it('does not apply a suggestion that a newer count replaced', function () {
    ($this->sell)(10);
    ($this->count)(2880, 'recipe');
    $old = ($this->line)();

    $this->travel(1)->day();
    ($this->count)(2800);

    $this->actingAs($this->owner)->post(route('admin.recipe-fixes.apply', $old))->assertSessionHasErrors('recipe');
    expect((float) $this->burger->recipeLines()->sole()->qty)->toBe(15.0);
});

it('keeps recipe suggestions inside their shop', function () {
    ($this->sell)(10);
    ($this->count)(2880, 'recipe');

    $this->actingAs(shopOwner())->post(route('admin.recipe-fixes.apply', ($this->line)()))->assertNotFound();
    expect(($this->line)()->recipe_fix)->toBeNull();
});

it('does not subtract stock purchases from profit, but does subtract supplies', function () {
    $log = fn (ExpenseCategory $category, float $amount) => Expense::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'date' => today(), 'category' => $category,
        'kind' => $category->defaultKind(), 'description' => $category->label(), 'amount' => $amount, 'logged_by' => 'Owner',
    ]);

    $log(ExpenseCategory::StockPurchase, 500);
    $log(ExpenseCategory::Supplies, 80);

    expect(($this->today)()['expenses'])->toBe(80.0)
        ->and(($this->today)()['stock_purchases'])->toBe(500.0)
        ->and(($this->today)()['net'])->toBe(-80.0);
});

it('logs the owner\'s restock as a stock purchase only for items whose use is costed', function () {
    $bags = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'piece', 'name' => 'Paper bag', 'unit' => 'pc', 'on_hand' => 0, 'unit_cost' => 1]);
    $buns = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'piece', 'name' => 'Bun', 'unit' => 'pc', 'on_hand' => 0, 'unit_cost' => 7]);
    $this->burger->recipeLines()->create(['piece_item_id' => $buns->id, 'qty' => 1]);

    foreach ([$bags, $buns, $this->ketchup] as $item) {
        $this->actingAs($this->owner)->post(route('admin.inventory.restock', $item), ['quantity' => 10, 'paid' => 100, 'log_expense' => 1])->assertRedirect();
    }

    expect(Expense::withoutGlobalScopes()->orderBy('id')->pluck('category')->all())
        ->toBe([ExpenseCategory::Supplies, ExpenseCategory::StockPurchase, ExpenseCategory::StockPurchase]);

    $this->business->update(['settings' => ['audit_pieces' => true]]);
    expect($bags->isCostedWhenUsed($this->business->refresh()))->toBeTrue();
});

it('starts the window at an older count\'s time when it predates the stored position', function () {
    ($this->sell)(10);
    ($this->count)(2850);
    AuditLine::query()->latest('id')->first()->audit()->withoutGlobalScopes()->update(['last_movement_id' => null]);

    $this->travel(1)->day();
    ($this->sell)(2);
    ($this->count)(2830, 'recipe');

    expect((float) ($this->line)()->recipe_deducted)->toBe(30.0);
});
