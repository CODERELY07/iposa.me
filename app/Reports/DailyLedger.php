<?php

namespace App\Reports;

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One row per day: the same columns as the owner's spreadsheet.
 *
 *   Net Profit    = Sales − COGS − Operating expenses
 *   Money Movement = Sales − Operating expenses − Restock costs
 *
 * COGS is the locked-in cost of exactly what sold (`order_lines.unit_cost`,
 * fixed at checkout — never recalculated from today's prices). A sale with an
 * unconfigured cost contributes nothing to COGS, which is why `coverage` exists:
 * it says what share of sales revenue actually had a known cost, so Net Profit's
 * trustworthiness is visible, not just its number. Restocks are a cash outflow,
 * not a profit expense — they convert cash into inventory, and only count once
 * that inventory is sold, via COGS. `bulk` is kept for the Bulk drill-down page
 * and the recipe-surplus math, but (like COGS) never feeds Money Movement.
 *
 * Every report and dashboard reads from here, so the numbers always match.
 */
class DailyLedger
{
    /**
     * @return Collection<string, array{date: CarbonImmutable, orders: int, sales: float, cogs: float, known_revenue: float, bulk: float, audited: bool, expenses: float, payables: float, missing: float, stock_purchases: float, net: float, money_movement: float}>
     */
    public function forRange(Business $business, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->endOfDay();

        $sales = $this->salesByDay($business, $from, $to);
        $cogs = $this->cogsByDay($business, $from, $to);
        $bulk = $this->bulkByDay($business, $from, $to);
        $auditedDays = $this->auditedDays($business, $from, $to);
        $expenses = $this->expensesByDay($business, $from, $to);

        $rows = collect();

        foreach (CarbonPeriod::create($from, '1 day', $to->startOfDay()) as $day) {
            $key = $day->toDateString();
            $row = [
                'date' => CarbonImmutable::parse($key),
                'orders' => (int) ($sales[$key]->orders ?? 0),
                'sales' => round((float) ($sales[$key]->sales ?? 0), 2),
                'cogs' => round((float) ($cogs[$key]->cogs ?? 0), 2),
                // Revenue from lines whose cost is actually known -- the numerator for coverage.
                'known_revenue' => round((float) ($cogs[$key]->known_revenue ?? 0), 2),
                'bulk' => round((float) ($bulk[$key]->bulk ?? 0), 2),
                // Whether the day was closed at all, regardless of whether any counted
                // item's extra usage was turned on to count against profit.
                'audited' => $auditedDays->has($key),
                'expenses' => round((float) ($expenses[$key]->expenses ?? 0), 2),
                'payables' => round((float) ($expenses[$key]->payables ?? 0), 2),
                'missing' => round((float) ($expenses[$key]->missing ?? 0), 2),
                'stock_purchases' => round((float) ($expenses[$key]->stock_purchases ?? 0), 2),
            ];
            $row['net'] = round($row['sales'] - $row['cogs'] - $row['expenses'], 2);
            $row['money_movement'] = round($row['sales'] - $row['expenses'] - $row['stock_purchases'], 2);

            $rows->put($key, $row);
        }

        return $rows;
    }

    /**
     * Column totals for a set of ledger rows. `coverage` is revenue-weighted (not
     * a count of items) so two unpriced items can't hide behind many cheap, priced
     * ones -- null when there were no sales to measure coverage against.
     *
     * @param  Collection<string, array<string, mixed>>  $rows
     * @return array{orders: int, sales: float, cogs: float, bulk: float, expenses: float, payables: float, missing: float, stock_purchases: float, net: float, money_movement: float, coverage: ?float}
     */
    public function totals(Collection $rows): array
    {
        $sales = round($rows->sum('sales'), 2);
        $knownRevenue = round($rows->sum('known_revenue'), 2);

        return [
            'orders' => (int) $rows->sum('orders'),
            'sales' => $sales,
            'cogs' => round($rows->sum('cogs'), 2),
            'bulk' => round($rows->sum('bulk'), 2),
            'expenses' => round($rows->sum('expenses'), 2),
            'payables' => round($rows->sum('payables'), 2),
            'missing' => round($rows->sum('missing'), 2),
            'stock_purchases' => round($rows->sum('stock_purchases'), 2),
            'net' => round($rows->sum('net'), 2),
            'money_movement' => round($rows->sum('money_movement'), 2),
            'coverage' => $sales > 0 ? round($knownRevenue / $sales * 100, 1) : null,
        ];
    }

    /**
     * Sales between two exact moments (e.g. "yesterday until this hour").
     */
    public function salesBetween(Business $business, CarbonInterface $from, CarbonInterface $to): float
    {
        return round((float) DB::table('orders')
            ->where('business_id', $business->id)
            ->whereIn('status', $this->countedStatuses())
            ->whereBetween('paid_at', [$from, $to])
            ->sum('subtotal'), 2);
    }

    /**
     * Top sellers by quantity, with revenue and margin.
     *
     * @return Collection<int, array{name: string, sold: int, revenue: float, margin: ?float}>
     */
    public function bestSellers(Business $business, CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        return DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.business_id', $business->id)
            ->whereIn('orders.status', $this->countedStatuses())
            ->whereBetween('orders.paid_at', [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()])
            ->groupBy('order_lines.name', 'order_lines.variant_label')
            ->selectRaw('order_lines.name, order_lines.variant_label, sum(order_lines.qty) as sold, sum(order_lines.qty * order_lines.price) as revenue, sum(order_lines.qty * order_lines.unit_cost) as cost')
            ->orderByDesc('sold')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'name' => $row->variant_label && ! in_array($row->variant_label, ['Regular', ''], true) ? $row->name.' '.$row->variant_label : $row->name,
                'sold' => (int) $row->sold,
                'revenue' => round((float) $row->revenue, 2),
                'margin' => (float) $row->revenue > 0 ? round(((float) $row->revenue - (float) $row->cost) / (float) $row->revenue * 100, 1) : null,
            ]);
    }

    /**
     * @return Collection<string, object>
     */
    private function salesByDay(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('orders')
            ->where('business_id', $business->id)
            ->whereIn('status', $this->countedStatuses())
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('date(paid_at) as day, count(*) as orders, sum(subtotal) as sales')
            ->groupByRaw('date(paid_at)')
            ->get()
            ->keyBy('day');
    }

    /**
     * @return Collection<string, object>
     */
    private function cogsByDay(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.business_id', $business->id)
            ->whereIn('orders.status', $this->countedStatuses())
            ->whereBetween('orders.paid_at', [$from, $to])
            ->selectRaw('date(orders.paid_at) as day, sum(order_lines.qty * order_lines.unit_cost) as cogs, sum(case when order_lines.unit_cost is not null then order_lines.qty * order_lines.price else 0 end) as known_revenue')
            ->groupByRaw('date(orders.paid_at)')
            ->get()
            ->keyBy('day');
    }

    /**
     * Days with a submitted closing audit, regardless of whether any item counted
     * that night has its extra usage set to count against profit.
     *
     * @return Collection<string, string>
     */
    private function auditedDays(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('audits')
            ->where('business_id', $business->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->pluck('date')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->flip();
    }

    /**
     * @return Collection<string, object>
     */
    private function bulkByDay(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('audit_lines')
            ->join('audits', 'audits.id', '=', 'audit_lines.audit_id')
            ->join('items', 'items.id', '=', 'audit_lines.item_id')
            ->where('audits.business_id', $business->id)
            ->whereBetween('audits.date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->where('items.include_audit_cost', true)
            // Used beyond the recipes, minus what the recipes over-charged in sales.
            ->selectRaw('date(audits.date) as day, sum((audit_lines.used - audit_lines.recipe_surplus_costed) * audit_lines.unit_cost) as bulk')
            ->groupByRaw('date(audits.date)')
            ->get()
            ->keyBy('day');
    }

    /**
     * @return Collection<string, object>
     */
    private function expensesByDay(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('expenses')
            ->where('business_id', $business->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->selectRaw(
                'date(date) as day, sum(case when category = ? then 0 else amount end) as expenses, sum(case when category = ? then amount else 0 end) as payables, sum(case when category = ? then amount else 0 end) as missing, sum(case when category = ? then amount else 0 end) as stock_purchases',
                [ExpenseCategory::StockPurchase->value, ExpenseCategory::Payables->value, ExpenseCategory::MissingStock->value, ExpenseCategory::StockPurchase->value],
            )
            ->groupByRaw('date(date)')
            ->get()
            ->keyBy('day');
    }

    /**
     * @return list<string>
     */
    private function countedStatuses(): array
    {
        return array_map(fn (OrderStatus $status) => $status->value, OrderStatus::countedAsSales());
    }
}
