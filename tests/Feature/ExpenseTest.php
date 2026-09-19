<?php

use App\Enums\ExpenseCategory;
use App\Models\Asset;
use App\Models\Expense;

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
