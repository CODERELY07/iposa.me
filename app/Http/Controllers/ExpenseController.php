<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseFrequency;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\RecurringExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    /**
     * Month view: quick-add row, entries, category breakdown, equipment & payables.
     */
    public function index(Request $request): View
    {
        $month = $this->selectedMonth($request);

        $expenses = Expense::query()
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString().' 23:59:59'])
            ->latest('date')
            ->latest('id')
            ->get();

        $byCategory = $expenses
            ->groupBy(fn (Expense $expense) => $expense->category->value)
            ->map(fn ($group, string $category) => [
                'category' => ExpenseCategory::from($category),
                'amount' => round($group->sum(fn (Expense $expense) => (float) $expense->amount), 2),
            ])
            ->sortByDesc('amount')
            ->values();

        $assets = Asset::query()->orderBy('name')->get();
        $dueThisMonth = $assets->filter(fn (Asset $asset) => $asset->nextDueOn()?->lte(now()->endOfMonth()));

        $recurring = RecurringExpense::query()->orderBy('next_due_on')->get();
        $dueRecurring = $recurring->filter(fn (RecurringExpense $item) => $item->isDue());

        $firstExpenseDate = Expense::query()->min('date');

        return view('admin.expenses', [
            'expenses' => $expenses,
            'byCategory' => $byCategory,
            'monthTotal' => round($expenses->sum(fn (Expense $expense) => (float) $expense->amount), 2),
            'month' => $month,
            'monthOptions' => $this->monthOptions($firstExpenseDate ? Carbon::parse($firstExpenseDate) : now()),
            'categories' => ExpenseCategory::selectable(),
            'assets' => $assets,
            'payablesThisMonth' => round($dueThisMonth->sum(fn (Asset $asset) => $asset->paymentAmount()), 2),
            'nextDueAsset' => $assets->filter(fn (Asset $asset) => $asset->nextDueOn() !== null)->sortBy(fn (Asset $asset) => $asset->nextDueOn())->first(),
            'recurring' => $recurring,
            'dueRecurring' => $dueRecurring,
            'frequencies' => ExpenseFrequency::cases(),
        ]);
    }

    /**
     * Log an expense (owners from the Expenses page, cashiers from My orders when allowed).
     * Safe to retry with the same uuid: the first entry is kept.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse|JsonResponse
    {
        $category = ExpenseCategory::from($request->validated('category'));
        $uuid = $request->validated('uuid');

        $existing = $uuid !== null ? Expense::query()->where('uuid', $uuid)->first() : null;

        $expense = $existing ?? Expense::create([
            'uuid' => $uuid,
            'date' => $request->validated('date'),
            'category' => $category,
            'description' => $request->validated('description'),
            'kind' => $request->validated('kind') ?? $category->defaultKind()->value,
            'amount' => $request->validated('amount'),
            'user_id' => $request->user()->id,
            'logged_by' => $request->user()->name,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['expense' => ['id' => $expense->id, 'amount' => (float) $expense->amount]], $expense->wasRecentlyCreated ? 201 : 200);
        }

        return back()->with('status', 'Expense added.');
    }

    /**
     * Correct a typo: date, category, description or amount. Payables and missing-stock
     * rows aren't editable here — they're kept in sync with the installment or delivery
     * that created them, so changing them by hand would drift the two apart.
     */
    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        if (in_array($expense->category, [ExpenseCategory::Payables, ExpenseCategory::MissingStock], true)) {
            return back()->withErrors(['expense' => "{$expense->category->label()} entries aren't edited here."]);
        }

        $category = ExpenseCategory::from($request->validated('category'));

        $expense->update([
            'date' => $request->validated('date'),
            'category' => $category,
            'description' => $request->validated('description'),
            'kind' => $request->validated('kind') ?? $category->defaultKind()->value,
            'amount' => $request->validated('amount'),
        ]);

        return back()->with('status', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        if ($expense->asset_id !== null) {
            $expense->asset?->decrement('paid_count');
        }

        $expense->delete();

        return back()->with('status', 'Expense deleted.');
    }

    private function selectedMonth(Request $request): Carbon
    {
        $value = (string) $request->query('month');

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        return now()->startOfMonth();
    }

    /**
     * From the first month with expenses up to this month, newest first.
     *
     * @return list<array{value: string, label: string}>
     */
    private function monthOptions(Carbon $earliest): array
    {
        $options = [];
        $cursor = now()->startOfMonth();
        $stop = $earliest->copy()->startOfMonth()->min(now()->startOfMonth()->subMonths(2));

        while ($cursor->gte($stop) && count($options) < 24) {
            $options[] = ['value' => $cursor->format('Y-m'), 'label' => $cursor->format('F Y')];
            $cursor->subMonth();
        }

        return $options;
    }
}
