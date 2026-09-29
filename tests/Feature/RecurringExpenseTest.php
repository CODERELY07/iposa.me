<?php

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseFrequency;
use App\Models\Expense;
use App\Models\RecurringExpense;

beforeEach(function () {
    $this->owner = shopOwner();
});

it('sets up a recurring expense', function () {
    $this->actingAs($this->owner)->post(route('admin.recurring-expenses.store'), [
        'description' => 'Shop rent', 'category' => 'rent', 'amount' => 15000, 'frequency' => 'monthly',
        'next_due_on' => today()->toDateString(),
    ])->assertRedirect(route('admin.expenses', ['tab' => 'recurring']));

    $recurring = RecurringExpense::withoutGlobalScopes()->sole();
    expect($recurring->description)->toBe('Shop rent')
        ->and($recurring->category)->toBe(ExpenseCategory::Rent)
        ->and((float) $recurring->amount)->toBe(15000.0)
        ->and($recurring->frequency)->toBe(ExpenseFrequency::Monthly)
        ->and($recurring->created_by_name)->toBe($this->owner->name)
        ->and($recurring->isDue())->toBeTrue();
});

it('refuses a first due date in the past, or the payables category', function () {
    $this->actingAs($this->owner)->post(route('admin.recurring-expenses.store'), [
        'description' => 'x', 'category' => 'payables', 'amount' => 1, 'frequency' => 'monthly',
        'next_due_on' => today()->subDay()->toDateString(),
    ])->assertSessionHasErrors(['category', 'next_due_on']);
});

it('is not due until its date, then posts an expense and moves to the next occurrence', function () {
    $recurring = RecurringExpense::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'description' => 'Shop rent', 'category' => 'rent',
        'amount' => 15000, 'frequency' => 'monthly', 'next_due_on' => today()->addDay(),
    ]);
    expect($recurring->isDue())->toBeFalse();

    $this->actingAs($this->owner)->post(route('admin.recurring-expenses.confirm', $recurring))
        ->assertSessionHasErrors('recurring');
    expect(Expense::withoutGlobalScopes()->count())->toBe(0);

    $recurring->update(['next_due_on' => today()]);

    $this->actingAs($this->owner)->post(route('admin.recurring-expenses.confirm', $recurring))
        ->assertRedirect(route('admin.expenses'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Shop rent added'));

    $expense = Expense::withoutGlobalScopes()->sole();
    expect($expense->category)->toBe(ExpenseCategory::Rent)
        ->and($expense->description)->toBe('Shop rent')
        ->and((float) $expense->amount)->toBe(15000.0)
        ->and($expense->date->toDateString())->toBe(today()->toDateString())
        ->and($expense->logged_by)->toBe($this->owner->name);

    expect($recurring->refresh()->next_due_on->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());
});

it('advances daily, weekly and yearly the same way', function (string $frequency, Closure $expected) {
    $recurring = RecurringExpense::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'description' => 'x', 'category' => 'misc',
        'amount' => 100, 'frequency' => $frequency, 'next_due_on' => today(),
    ]);

    $this->actingAs($this->owner)->post(route('admin.recurring-expenses.confirm', $recurring))->assertRedirect();

    expect($recurring->refresh()->next_due_on->toDateString())->toBe($expected(today())->toDateString());
})->with([
    'daily' => ['daily', fn ($date) => $date->addDay()],
    'weekly' => ['weekly', fn ($date) => $date->addWeek()],
    'yearly' => ['yearly', fn ($date) => $date->addYearNoOverflow()],
]);

it('removes a recurring expense without touching expenses it already created', function () {
    $recurring = RecurringExpense::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'description' => 'Shop rent', 'category' => 'rent',
        'amount' => 15000, 'frequency' => 'monthly', 'next_due_on' => today(),
    ]);
    $this->actingAs($this->owner)->post(route('admin.recurring-expenses.confirm', $recurring));

    $this->actingAs($this->owner)->delete(route('admin.recurring-expenses.destroy', $recurring))->assertRedirect();

    expect(RecurringExpense::withoutGlobalScopes()->count())->toBe(0)
        ->and(Expense::withoutGlobalScopes()->count())->toBe(1);
});

it('shows a due reminder on the daily expenses tab', function () {
    RecurringExpense::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'description' => 'Shop rent', 'category' => 'rent',
        'amount' => 15000, 'frequency' => 'monthly', 'next_due_on' => today(),
    ]);

    $this->actingAs($this->owner)->get(route('admin.expenses'))
        ->assertOk()
        ->assertSee('A bill is due')
        ->assertSee('Shop rent', false);
});

it('cannot see or confirm another shop\'s recurring expense', function () {
    $recurring = RecurringExpense::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'description' => 'x', 'category' => 'misc',
        'amount' => 100, 'frequency' => 'monthly', 'next_due_on' => today(),
    ]);

    $stranger = shopOwner();
    $this->actingAs($stranger)->post(route('admin.recurring-expenses.confirm', $recurring))->assertNotFound();
});
