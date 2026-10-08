<?php

use App\Enums\ExpenseCategory;
use App\Models\Asset;
use App\Models\Expense;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->owner = shopOwner();
});

it('logs an expense like a spreadsheet row', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'Ice, 3 sacks', 'amount' => 300,
    ])->assertRedirect()->assertSessionHas('status');

    $expense = Expense::withoutGlobalScopes()->sole();
    expect($expense->category)->toBe(ExpenseCategory::Supplies)
        ->and($expense->kind->value)->toBe('variable')
        ->and($expense->logged_by)->toBe($this->owner->name)
        ->and($expense->business_id)->toBe($this->owner->business_id);
});

it('does not accept future dates or the payables category', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), [
        'date' => today()->addDay()->toDateString(), 'category' => 'payables', 'description' => 'x', 'amount' => 1,
    ])->assertSessionHasErrors(['date', 'category']);
});

it('corrects an expense already logged', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'Ice', 'amount' => 300,
    ]);
    $expense = Expense::withoutGlobalScopes()->sole();

    $this->actingAs($this->owner)->put(route('admin.expenses.update', $expense), [
        'date' => today()->subDay()->toDateString(), 'category' => 'misc', 'description' => 'Ice, corrected', 'amount' => 250,
    ])->assertRedirect()->assertSessionHas('status', 'Expense updated.');

    expect($expense->refresh()->category)->toBe(ExpenseCategory::Misc)
        ->and($expense->description)->toBe('Ice, corrected')
        ->and((float) $expense->amount)->toBe(250.0)
        ->and($expense->date->toDateString())->toBe(today()->subDay()->toDateString());
});

it('refuses to edit a payables or missing-stock expense here', function () {
    $asset = Asset::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'name' => 'Fryer', 'price' => 12000, 'terms' => 1, 'paid_count' => 1, 'first_due_on' => today(),
    ]);
    $expense = Expense::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'date' => today(), 'category' => 'payables', 'description' => 'Fryer',
        'kind' => 'fixed', 'amount' => 12000, 'asset_id' => $asset->id, 'logged_by' => $this->owner->name,
    ]);

    $this->actingAs($this->owner)->put(route('admin.expenses.update', $expense), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'x', 'amount' => 1,
    ])->assertSessionHasErrors('expense');

    expect($expense->refresh()->amount)->toBe('12000.00');
});

it('cannot edit another shop\'s expense', function () {
    $expense = Expense::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'date' => today(), 'category' => 'supplies', 'description' => 'Ice',
        'kind' => 'variable', 'amount' => 100, 'logged_by' => $this->owner->name,
    ]);

    $stranger = shopOwner();
    $this->actingAs($stranger)->put(route('admin.expenses.update', $expense), [
        'date' => today()->toDateString(), 'category' => 'misc', 'description' => 'x', 'amount' => 1,
    ])->assertNotFound();
});

it('lets a cashier log a small expense when allowed', function () {
    $cashier = cashierOf($this->owner);

    $this->actingAs($cashier)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'LPG', 'amount' => 1050,
    ])->assertRedirect();

    $this->owner->business->update(['settings' => ['cashier_permissions' => ['log_expenses' => false]]]);

    $this->actingAs($cashier->fresh())->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'LPG', 'amount' => 1050,
    ])->assertForbidden();

    expect(Expense::withoutGlobalScopes()->count())->toBe(1);
});

it('shows the month with totals by category', function () {
    Expense::withoutGlobalScopes()->create(['business_id' => $this->owner->business_id, 'date' => today(), 'category' => 'rent', 'description' => 'Rent', 'kind' => 'fixed', 'amount' => 18000, 'logged_by' => 'Maria']);

    $this->actingAs($this->owner)->get(route('admin.expenses'))
        ->assertOk()
        ->assertSee('₱18,000.00', false)
        ->assertViewHas('monthTotal', 18000.0);
});

it('records an installment as an expense exactly once', function () {
    $this->actingAs($this->owner)->post(route('admin.assets.store'), [
        'name' => 'Espresso machine', 'price' => 85000, 'terms' => 12, 'installment_amount' => 7083.33, 'first_due_on' => today()->toDateString(),
    ])->assertRedirect(route('admin.expenses', ['tab' => 'assets']));

    $asset = Asset::withoutGlobalScopes()->sole();
    expect($asset->nextDueOn()->toDateString())->toBe(today()->toDateString());

    $this->actingAs($this->owner)->post(route('admin.assets.pay', $asset));

    expect($asset->refresh()->paid_count)->toBe(1)
        ->and($asset->nextDueOn()->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString())
        ->and(Expense::withoutGlobalScopes()->where('category', 'payables')->sum('amount'))->toEqual(7083.33);
});

it('logs a cash equipment purchase right away', function () {
    $this->actingAs($this->owner)->post(route('admin.assets.store'), [
        'name' => 'Griddle', 'price' => 12500, 'terms' => 1, 'first_due_on' => today()->toDateString(),
    ]);

    expect(Asset::withoutGlobalScopes()->sole()->isFullyPaid())->toBeTrue()
        ->and((float) Expense::withoutGlobalScopes()->sole()->amount)->toBe(12500.0);
});

it('rolls back an installment when its expense is deleted', function () {
    $this->actingAs($this->owner)->post(route('admin.assets.store'), [
        'name' => 'Blender', 'price' => 9800, 'terms' => 6, 'installment_amount' => 1633.33, 'first_due_on' => today()->toDateString(),
    ]);
    $asset = Asset::withoutGlobalScopes()->sole();
    $this->actingAs($this->owner)->post(route('admin.assets.pay', $asset));

    $this->actingAs($this->owner)->delete(route('admin.expenses.destroy', Expense::withoutGlobalScopes()->sole()));

    expect($asset->refresh()->paid_count)->toBe(0);
});

it('keeps expenses behind the Negosyo plan', function () {
    $this->owner->business->update(['plan' => 'tindahan']);

    $this->actingAs($this->owner)->get(route('admin.expenses'))->assertRedirect(route('admin.settings'));
});

it('records an expense logged offline only once, however often it is replayed', function () {
    $cashier = cashierOf($this->owner);
    $payload = [
        'uuid' => (string) Str::uuid(), 'date' => today()->toDateString(), 'category' => 'supplies',
        'description' => 'Ice', 'amount' => 150,
    ];

    $this->actingAs($cashier)->postJson(route('expenses.store'), $payload)->assertCreated()->assertJsonPath('expense.amount', 150);
    $this->actingAs($cashier)->postJson(route('expenses.store'), $payload)->assertOk();

    expect(Expense::withoutGlobalScopes()->count())->toBe(1)
        ->and(Expense::withoutGlobalScopes()->sole()->uuid)->toBe($payload['uuid']);
});

it('keeps the same expense id for two different shops', function () {
    $uuid = (string) Str::uuid();
    $other = shopOwner();

    foreach ([$this->owner, $other] as $owner) {
        $this->actingAs($owner)->postJson(route('expenses.store'), [
            'uuid' => $uuid, 'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'Ice', 'amount' => 50,
        ])->assertCreated();
    }

    expect(Expense::withoutGlobalScopes()->count())->toBe(2);
});

it('still takes an expense typed in without an id', function () {
    $this->actingAs($this->owner)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'Ice', 'amount' => 80,
    ])->assertRedirect();
    $this->actingAs($this->owner)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'Ice', 'amount' => 80,
    ])->assertRedirect();

    expect(Expense::withoutGlobalScopes()->count())->toBe(2);
});
