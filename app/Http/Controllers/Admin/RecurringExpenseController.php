<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRecurringExpenseRequest;
use App\Models\Expense;
use App\Models\RecurringExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecurringExpenseController extends Controller
{
    /**
     * Set up a bill that repeats: rent, wages, a subscription.
     */
    public function store(StoreRecurringExpenseRequest $request): RedirectResponse
    {
        RecurringExpense::create([
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'amount' => $request->validated('amount'),
            'frequency' => $request->validated('frequency'),
            'next_due_on' => $request->validated('next_due_on'),
            'created_by' => $request->user()->id,
            'created_by_name' => $request->user()->name,
        ]);

        return redirect()->route('admin.expenses', ['tab' => 'recurring'])->with('status', 'Recurring expense added.');
    }

    /**
     * Post this occurrence as a real expense (editable afterward like any other),
     * and move the reminder to its next date.
     */
    public function confirm(Request $request, RecurringExpense $recurring): RedirectResponse
    {
        if (! $recurring->isDue()) {
            throw ValidationException::withMessages(['recurring' => "{$recurring->description} isn't due yet."]);
        }

        Expense::create([
            'date' => today(),
            'category' => $recurring->category,
            'description' => $recurring->description,
            'kind' => $recurring->category->defaultKind(),
            'amount' => $recurring->amount,
            'user_id' => $request->user()->id,
            'logged_by' => $request->user()->name,
        ]);

        $recurring->update(['next_due_on' => $recurring->frequency->next($recurring->next_due_on)]);

        return redirect()->route('admin.expenses')->with('status', "{$recurring->description} added to today's expenses.");
    }

    public function destroy(RecurringExpense $recurring): RedirectResponse
    {
        $recurring->delete();

        return redirect()->route('admin.expenses', ['tab' => 'recurring'])->with('status', 'Recurring expense removed. Past entries stay in your expenses.');
    }
}
