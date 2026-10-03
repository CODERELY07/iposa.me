<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseCategory;
use App\Enums\ItemKind;
use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Order;
use App\Models\RecipeChange;
use App\Models\RecipeLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Reports\DailyLedger;
use App\Reports\RecipeVariance;
use App\Services\Audit\RecipeFixService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Today: true profit so far, what needs attention tonight, the last 7 days.
     */
    public function __invoke(Request $request, DailyLedger $ledger, RecipeVariance $variance, RecipeFixService $recipeFixes): View
    {
        $business = $request->user()->business;

        $week = $ledger->forRange($business, today()->subDays(6), today());
        $today = $week->last();
        $expenseCount = Expense::query()->whereDate('date', today())->whereNot('category', ExpenseCategory::StockPurchase)->count();
        $stockPurchaseCount = Expense::query()->whereDate('date', today())->where('category', ExpenseCategory::StockPurchase)->count();

        // Every one of today's orders (voided ones too) and expenses:
        // what "reset today" would actually remove, not just what the equation above shows.
        $ordersToday = Order::query()->whereBetween('paid_at', [today(), today()->endOfDay()])->count();
        $expensesToday = Expense::query()->whereDate('date', today())->count();

        $yesterdaySalesSoFar = $ledger->salesBetween($business, today()->subDay(), now()->subDay());
        $yesterdayExpenses = (float) Expense::query()->whereDate('date', today()->subDay())->sum('amount');
        $profitSoFar = $today['net'];
        $yesterdayProfitAtThisHour = round($yesterdaySalesSoFar - $yesterdayExpenses, 2);
        $todayCoverage = $today['sales'] > 0 ? round($today['known_revenue'] / $today['sales'] * 100, 1) : null;

        return view('admin.dashboard', [
            'business' => $business,
            'today' => $today + ['expenseCount' => $expenseCount, 'stockPurchaseCount' => $stockPurchaseCount, 'coverage' => $todayCoverage],
            'profitSoFar' => $profitSoFar,
            'profitDelta' => round($profitSoFar - $yesterdayProfitAtThisHour, 2),
            'week' => $week->values(),
            'bestSellers' => $ledger->bestSellers($business, today(), today()),
            'lowStock' => $this->lowStock($business),
            'voidRequests' => Order::query()->where('status', OrderStatus::VoidRequested)->with('lines')->latest('paid_at')->get(),
            'auditDone' => $today['audited'],
            'setupSteps' => $this->setupSteps(),
            'recipeVariance' => $variance->latest($business),
            'deliveries' => Delivery::query()->where('status', Delivery::PENDING)->with(['item' => fn ($query) => $query->withoutGlobalScopes()])->oldest('id')->get(),
            'recipeRequests' => RecipeChange::query()->where('status', RecipeChange::PENDING)->with('item')->oldest('id')->get(),
            'reopenRequests' => Audit::query()->whereDate('date', today())->where('reopen_status', Audit::REOPEN_PENDING)->get(),
            'recipeFixes' => $recipeFixes->suggestions($business),
            'expensesEnabled' => $business->hasFeature('expenses'),
            'ordersToday' => $ordersToday,
            'expensesToday' => $expensesToday,
        ]);
    }

    /**
     * Tracked items at or below their alert level, with a rough "runs out" estimate.
     *
     * @return Collection<int, array{name: string, left: string, runsOut: ?string}>
     */
    private function lowStock($business): Collection
    {
        $items = Item::query()->active()->whereNotNull('on_hand')->orderBy('on_hand')->get()
            ->filter(fn (Item $item) => $item->isLowStock($business))
            ->take(5);

        // Sizes that keep their own count (bottled water 500ml vs 1L) each have their own alert.
        $sizes = ItemVariant::query()->with('item')->whereNotNull('on_hand')
            ->whereHas('item', fn ($query) => $query->active())
            ->orderBy('on_hand')->get()
            ->filter(fn (ItemVariant $size) => $size->item->isVariantLowStock($size, $business))
            ->take(5);

        if ($items->isEmpty() && $sizes->isEmpty()) {
            return collect();
        }

        $usage = fn (string $column, $ids) => StockMovement::query()
            ->whereIn($column, $ids)
            ->whereIn('reason', [StockMovementReason::Sale, StockMovementReason::Audit])
            ->where('qty_change', '<', 0)
            ->where('created_at', '>=', today()->subDays(7))
            ->selectRaw("{$column}, -sum(qty_change) / 7 as per_day")
            ->groupBy($column)
            ->pluck('per_day', $column);

        $itemUse = $usage('item_id', $items->pluck('id'));
        $sizeUse = $usage('item_variant_id', $sizes->pluck('id'));

        $runsOut = fn (?float $daysLeft): ?string => match (true) {
            $daysLeft === null => null,
            $daysLeft < 1 => 'today at this pace',
            $daysLeft < 2 => 'tomorrow at this pace',
            default => 'in about '.(int) floor($daysLeft).' days',
        };

        $itemRows = $items->map(function (Item $item) use ($itemUse, $runsOut): array {
            $perDay = (float) ($itemUse[$item->id] ?? 0);

            return [
                'name' => $item->name,
                'left' => rtrim(rtrim(number_format((float) $item->on_hand, 2), '0'), '.').' '.($item->unit ?: ($item->kind === ItemKind::Piece ? 'pcs' : 'left')),
                'runsOut' => $runsOut($perDay > 0 ? max(0, (float) $item->on_hand) / $perDay : null),
            ];
        });

        $sizeRows = $sizes->map(function (ItemVariant $size) use ($sizeUse, $runsOut): array {
            $perDay = (float) ($sizeUse[$size->id] ?? 0);

            return [
                'name' => $size->item->name.' · '.$size->label,
                'left' => rtrim(rtrim(number_format((float) $size->on_hand, 2), '0'), '.').' left',
                'runsOut' => $runsOut($perDay > 0 ? max(0, (float) $size->on_hand) / $perDay : null),
            ];
        });

        return $itemRows->concat($sizeRows)->take(5)->values();
    }

    /**
     * Onboarding checklist from real data. Hidden once everything is done.
     *
     * @return list<array{label: string, done: bool}>
     */
    private function setupSteps(): array
    {
        return [
            ['label' => 'Add your menu', 'done' => Item::query()->ofKind(ItemKind::Menu)->exists()],
            ['label' => 'Add bulk & liquids', 'done' => Item::query()->ofKind(ItemKind::Bulk)->exists()],
            ['label' => 'Link ingredients', 'done' => RecipeLine::query()->whereHas('item')->exists()],
            ['label' => 'Invite a cashier', 'done' => User::query()->where('business_id', auth()->user()->business_id)->where('role', User::ROLE_STAFF)->exists()],
            ['label' => 'Ring up a sale', 'done' => Order::query()->exists()],
            ['label' => 'Finish a closing audit', 'done' => Audit::query()->exists()],
        ];
    }
}
