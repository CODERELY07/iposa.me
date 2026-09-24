<?php

namespace App\Reports;

use App\Enums\ExpenseCategory;
use App\Enums\ItemKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementReason;
use App\Models\Audit;
use App\Models\AuditLine;
use App\Models\Business;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Every peso behind one day's profit, one tile at a time. Each total here reads
 * the same rows as DailyLedger, so it always matches the number on the Today page.
 */
class DayBreakdown
{
    /**
     * Every order of the day, voided ones included (they are shown but not counted).
     *
     * @return array{orders: Collection<int, array{order: Order, counted: bool, cost: float, profit: float}>, byMethod: Collection<string, array{label: string, orders: int, amount: float}>, total: float, cost: float, count: int}
     */
    public function sales(Business $business, CarbonImmutable $day): array
    {
        $counted = OrderStatus::countedAsSales();

        $orders = Order::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('paid_at', [$day->startOfDay(), $day->endOfDay()])
            ->with('lines')
            ->latest('paid_at')
            ->get()
            ->map(function (Order $order) use ($counted): array {
                $cost = round($order->lines->sum(fn (OrderLine $line) => $line->qty * (float) $line->unit_cost), 2);

                return [
                    'order' => $order,
                    'counted' => in_array($order->status, $counted, true),
                    'cost' => $cost,
                    'profit' => round((float) $order->subtotal - $cost, 2),
                ];
            });

        $countedOrders = $orders->where('counted', true);

        $byMethod = collect(PaymentMethod::cases())
            ->mapWithKeys(fn (PaymentMethod $method) => [$method->value => [
                'label' => $method->label(),
                'orders' => $countedOrders->filter(fn (array $row) => $row['order']->payment_method === $method)->count(),
                'amount' => round($countedOrders->filter(fn (array $row) => $row['order']->payment_method === $method)->sum(fn (array $row) => (float) $row['order']->subtotal), 2),
            ]])
            ->filter(fn (array $method) => $method['orders'] > 0);

        return [
            'orders' => $orders,
            'byMethod' => $byMethod,
            'total' => round($countedOrders->sum(fn (array $row) => (float) $row['order']->subtotal), 2),
            'cost' => round($countedOrders->sum('cost'), 2),
            'count' => $countedOrders->count(),
        ];
    }

    /**
     * What the day's sales cost (per size sold), and what they took off the shelf.
     *
     * @return array{sold: Collection<int, array{name: string, qty: int, cost_each: float, cost: float, sales: float}>, total: float, taken: Collection<int, array{name: string, kind: ItemKind, unit: string, qty: float, costed: float, uncosted: float}>}
     */
    public function ingredients(Business $business, CarbonImmutable $day): array
    {
        $orderIds = Order::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereIn('status', OrderStatus::countedAsSales())
            ->whereBetween('paid_at', [$day->startOfDay(), $day->endOfDay()])
            ->pluck('id');

        $lines = OrderLine::query()->whereIn('order_id', $orderIds)->get();

        $sold = $lines
            ->groupBy(fn (OrderLine $line) => $line->name.'|'.$line->variant_label)
            ->map(function (Collection $lines): array {
                $first = $lines->first();
                $qty = (int) $lines->sum('qty');
                $cost = round($lines->sum(fn (OrderLine $line) => $line->qty * (float) $line->unit_cost), 2);

                return [
                    'name' => $first->name.($first->variant_label && $first->variant_label !== 'Regular' ? ' · '.$first->variant_label : ''),
                    'qty' => $qty,
                    // The cost can change during the day; this is the day's average per sale.
                    'cost_each' => $qty > 0 ? round($cost / $qty, 2) : 0.0,
                    'cost' => $cost,
                    'sales' => round($lines->sum(fn (OrderLine $line) => $line->qty * (float) $line->price), 2),
                ];
            })
            ->sortByDesc('cost')
            ->values();

        $movements = StockMovement::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('reason', StockMovementReason::Sale)
            ->whereIn('order_id', $orderIds)
            ->selectRaw('item_id, -sum(qty_change) as qty, -sum(costed_qty) as costed')
            ->groupBy('item_id')
            ->get();

        $items = Item::withoutGlobalScopes()->whereKey($movements->pluck('item_id'))->get()->keyBy('id');

        $taken = $movements
            ->filter(fn ($movement) => $items->has($movement->item_id))
            ->map(function ($movement) use ($items): array {
                $item = $items->get($movement->item_id);
                $qty = round((float) $movement->qty, 3);
                $costed = round((float) $movement->costed, 3);

                return [
                    'name' => $item->name,
                    'kind' => $item->kind,
                    'unit' => $item->unit ?: ($item->kind === ItemKind::Piece ? 'pc' : ''),
                    'qty' => $qty,
                    'costed' => $costed,
                    'uncosted' => max(0.0, round($qty - $costed, 3)),
                ];
            })
            ->sortBy('name')
            ->values();

        // Rounded once, like the ledger, so it matches the Today page to the centavo.
        $total = round($lines->sum(fn (OrderLine $line) => $line->qty * (float) $line->unit_cost), 2);

        return ['sold' => $sold, 'total' => $total, 'taken' => $taken];
    }

    /**
     * The closing count of the day, line by line, as it lowers profit.
     *
     * @return array{audit: ?Audit, lines: Collection<int, array{name: string, unit: string, expected: float, counted: float, used: float, restocked: float, recipe_surplus: float, credit: float, unit_cost: float, cost: float}>, total: float}
     */
    public function bulk(Business $business, CarbonImmutable $day): array
    {
        $audit = Audit::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereDate('date', $day->toDateString())
            ->with(['lines.item' => fn ($query) => $query->withoutGlobalScopes()])
            ->first();

        $auditLines = collect($audit?->lines ?? []);
        $total = round($auditLines->sum(fn (AuditLine $line) => ((float) $line->used - (float) $line->recipe_surplus_costed) * (float) $line->unit_cost), 2);

        $lines = $auditLines
            ->map(fn (AuditLine $line): array => [
                'name' => $line->item?->name ?? 'Deleted item',
                'unit' => $line->item?->unit ?: ($line->item?->kind === ItemKind::Piece ? 'pc' : ''),
                'expected' => (float) $line->expected,
                'counted' => (float) $line->counted,
                'used' => (float) $line->used,
                'restocked' => (float) $line->restocked,
                'recipe_surplus' => (float) $line->recipe_surplus,
                'credit' => round((float) $line->recipe_surplus_costed * (float) $line->unit_cost, 2),
                'unit_cost' => (float) $line->unit_cost,
                'cost' => round(((float) $line->used - (float) $line->recipe_surplus_costed) * (float) $line->unit_cost, 2),
            ])
            ->sortByDesc('cost')
            ->values();

        return ['audit' => $audit, 'lines' => $lines, 'total' => $total];
    }

    /**
     * The day's expenses. Stock purchases are listed but not counted in profit.
     *
     * @return array{entries: Collection<int, Expense>, total: float, stockPurchases: float}
     */
    public function expenses(Business $business, CarbonImmutable $day): array
    {
        $entries = Expense::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereDate('date', $day->toDateString())
            ->latest('id')
            ->get();

        return [
            'entries' => $entries,
            'total' => round($entries->filter(fn (Expense $expense) => $expense->category->lowersProfit())->sum(fn (Expense $expense) => (float) $expense->amount), 2),
            'stockPurchases' => round($entries->where('category', ExpenseCategory::StockPurchase)->sum(fn (Expense $expense) => (float) $expense->amount), 2),
        ];
    }
}
