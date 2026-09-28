<?php

namespace App\Reports;

use App\Enums\ItemKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementReason;
use App\Models\Business;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything an owner needs about a period, in one place, for the PDF report.
 *
 * Profit numbers come from DailyLedger, so the PDF always agrees with Today,
 * the Profit & ledger page and the CSV exports.
 */
class PeriodReport
{
    public function __construct(private DailyLedger $ledger) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Business $business, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = $this->ledger->forRange($business, $from, $to)->values();
        $totals = $this->ledger->totals($days);
        $orders = $this->orders($business, $from, $to);
        $menu = $this->menu($business, $from, $to);

        return [
            'business' => $business,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'days' => $days,
            'totals' => $totals,
            'margin' => $totals['sales'] > 0 ? round($totals['net'] / $totals['sales'] * 100, 1) : null,
            'auditedDays' => $days->where('audited', true)->count(),
            'payments' => $this->payments($orders),
            'weekdays' => $this->weekdays($orders),
            'hours' => $this->hours($orders),
            'menu' => $menu,
            'stockUsed' => $this->stockUsed($business, $from, $to),
            'closingCounts' => $this->closingCounts($business, $from, $to),
            'deliveries' => $this->deliveries($business, $from, $to),
            'pendingDeliveries' => Delivery::withoutGlobalScopes()->where('business_id', $business->id)->where('status', Delivery::PENDING)->count(),
            'stock' => $this->stock($business),
            'expenseCategories' => $this->expenseCategories($business, $from, $to),
            'expenses' => $this->expenses($business, $from, $to),
            'cashiers' => $this->cashiers($orders),
        ];
    }

    /**
     * The period's orders, only the columns the breakdowns need.
     *
     * @return Collection<int, Order>
     */
    private function orders(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['id', 'paid_at', 'subtotal', 'payment_method', 'cashier_name', 'status']);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Order>
     */
    private function counted(Collection $orders): Collection
    {
        return $orders->filter(fn (Order $order) => in_array($order->status, OrderStatus::countedAsSales(), true));
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{label: string, orders: int, amount: float}>
     */
    private function payments(Collection $orders): Collection
    {
        $counted = $this->counted($orders);

        return collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $method) => [
                'label' => $method->label(),
                'orders' => $counted->where('payment_method', $method)->count(),
                'amount' => round($counted->where('payment_method', $method)->sum(fn (Order $order) => (float) $order->subtotal), 2),
            ])
            ->filter(fn (array $row) => $row['orders'] > 0)
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * Average sales per weekday, so a month with five Fridays doesn't flatter Friday.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{label: string, days: int, amount: float, average: float}>
     */
    private function weekdays(Collection $orders): Collection
    {
        $counted = $this->counted($orders);

        return collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
            ->map(function (string $label, int $index) use ($counted): array {
                $matching = $counted->filter(fn (Order $order) => $order->paid_at->dayOfWeekIso === $index + 1);
                $days = $matching->map(fn (Order $order) => $order->paid_at->toDateString())->unique()->count();
                $amount = round($matching->sum(fn (Order $order) => (float) $order->subtotal), 2);

                return ['label' => $label, 'days' => $days, 'amount' => $amount, 'average' => $days > 0 ? round($amount / $days, 2) : 0.0];
            });
    }

    /**
     * Sales per hour of the day, only the hours the shop sold anything.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{label: string, orders: int, amount: float}>
     */
    private function hours(Collection $orders): Collection
    {
        return $this->counted($orders)
            ->groupBy(fn (Order $order) => (int) $order->paid_at->format('G'))
            ->sortKeys()
            ->map(fn (Collection $group, int $hour) => [
                'label' => CarbonImmutable::today()->setTime($hour, 0)->format('g A'),
                'orders' => $group->count(),
                'amount' => round($group->sum(fn (Order $order) => (float) $order->subtotal), 2),
            ])
            ->values();
    }

    /**
     * Every size sold: quantity, sales, cost and profit, most profitable first.
     *
     * @return Collection<int, array{name: string, sold: int, sales: float, cost: float, profit: float, margin: ?float}>
     */
    private function menu(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.business_id', $business->id)
            ->whereIn('orders.status', array_map(fn (OrderStatus $status) => $status->value, OrderStatus::countedAsSales()))
            ->whereBetween('orders.paid_at', [$from, $to])
            ->groupBy('order_lines.name', 'order_lines.variant_label')
            ->selectRaw('order_lines.name, order_lines.variant_label, sum(order_lines.qty) as sold, sum(order_lines.qty * order_lines.price) as sales, sum(order_lines.qty * order_lines.unit_cost) as cost')
            ->get()
            ->map(function (object $row): array {
                $sales = round((float) $row->sales, 2);
                $cost = round((float) $row->cost, 2);

                return [
                    'name' => $row->name.($row->variant_label && $row->variant_label !== 'Regular' ? ' · '.$row->variant_label : ''),
                    'sold' => (int) $row->sold,
                    'sales' => $sales,
                    'cost' => $cost,
                    'profit' => round($sales - $cost, 2),
                    'margin' => $sales > 0 ? round(($sales - $cost) / $sales * 100, 1) : null,
                ];
            })
            ->sortByDesc('profit')
            ->values();
    }

    /**
     * What sales took off the shelf per piece or liquid, and how much of it their cost included.
     *
     * @return Collection<int, array{name: string, unit: string, qty: float, costed: float, value: float}>
     */
    private function stockUsed(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = DB::table('stock_movements')
            ->join('orders', 'orders.id', '=', 'stock_movements.order_id')
            ->where('stock_movements.business_id', $business->id)
            ->where('stock_movements.reason', StockMovementReason::Sale->value)
            ->whereIn('orders.status', array_map(fn (OrderStatus $status) => $status->value, OrderStatus::countedAsSales()))
            ->whereBetween('orders.paid_at', [$from, $to])
            ->groupBy('stock_movements.item_id')
            ->selectRaw('stock_movements.item_id, -sum(stock_movements.qty_change) as qty, -sum(stock_movements.costed_qty) as costed')
            ->get();

        $items = Item::withoutGlobalScopes()->whereKey($rows->pluck('item_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn (object $row) => $items->has($row->item_id) && $items[$row->item_id]->kind !== ItemKind::Menu)
            ->map(function (object $row) use ($items): array {
                $item = $items[$row->item_id];

                return [
                    'name' => $item->name,
                    'unit' => $item->unit ?: 'pc',
                    'qty' => round((float) $row->qty, 3),
                    'costed' => round((float) $row->costed, 3),
                    'value' => round((float) $row->qty * (float) ($item->unit_cost ?? 0), 2),
                ];
            })
            ->sortByDesc('value')
            ->values();
    }

    /**
     * Closing counts over the period, per item: used or missing beyond the recipes, and their cost.
     *
     * @return Collection<int, array{name: string, unit: string, counts: int, used: float, cost: float, recipe_surplus: float, credit: float, restocked: float}>
     */
    private function closingCounts(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = DB::table('audit_lines')
            ->join('audits', 'audits.id', '=', 'audit_lines.audit_id')
            ->where('audits.business_id', $business->id)
            ->whereBetween('audits.date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->groupBy('audit_lines.item_id')
            ->selectRaw('audit_lines.item_id, count(*) as counts, sum(audit_lines.used) as used, sum(audit_lines.used * audit_lines.unit_cost) as used_cost, sum(audit_lines.recipe_surplus) as recipe_surplus, sum(audit_lines.recipe_surplus_costed * audit_lines.unit_cost) as credit, sum(audit_lines.restocked) as restocked')
            ->get();

        $items = Item::withoutGlobalScopes()->whereKey($rows->pluck('item_id'))->get()->keyBy('id');

        return $rows
            ->map(fn (object $row) => [
                'name' => $items[$row->item_id]->name ?? 'Deleted item',
                'unit' => ($items[$row->item_id]->unit ?? null) ?: 'pc',
                'counts' => (int) $row->counts,
                'used' => round((float) $row->used, 3),
                'cost' => round((float) $row->used_cost - (float) $row->credit, 2),
                'recipe_surplus' => round((float) $row->recipe_surplus, 3),
                'credit' => round((float) $row->credit, 2),
                'restocked' => round((float) $row->restocked, 3),
            ])
            ->sortByDesc('cost')
            ->values();
    }

    /**
     * @return Collection<int, Delivery>
     */
    private function deliveries(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Delivery::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$from, $to])
            ->with(['item' => fn ($query) => $query->withoutGlobalScopes()])
            ->oldest('id')
            ->get();
    }

    /**
     * What is on the shelf right now and what it is worth at its cost per unit.
     *
     * @return array{rows: Collection<int, array{name: string, kind: string, on_hand: float, unit: string, unit_cost: float, value: float, low: bool}>, value: float, low: int}
     */
    private function stock(Business $business): array
    {
        $rows = Item::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereNull('archived_at')
            ->whereIn('kind', [ItemKind::Piece, ItemKind::Bulk])
            ->whereNotNull('on_hand')
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'name' => $item->name,
                'kind' => $item->kind === ItemKind::Piece ? 'Piece' : 'Bulk',
                'on_hand' => (float) $item->on_hand,
                'unit' => $item->unit ?: 'pc',
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'value' => round(max(0.0, (float) $item->on_hand) * (float) ($item->unit_cost ?? 0), 2),
                'low' => $item->isLowStock($business),
            ]);

        return ['rows' => $rows, 'value' => round($rows->sum('value'), 2), 'low' => $rows->where('low', true)->count()];
    }

    /**
     * @return Collection<int, array{label: string, entries: int, amount: float, lowers_profit: bool}>
     */
    private function expenseCategories(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Expense::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->get(['category', 'amount'])
            ->groupBy(fn (Expense $expense) => $expense->category->value)
            ->map(fn (Collection $group) => [
                'label' => $group->first()->category->label(),
                'entries' => $group->count(),
                'amount' => round($group->sum(fn (Expense $expense) => (float) $expense->amount), 2),
                'lowers_profit' => $group->first()->category->lowersProfit(),
            ])
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * @return Collection<int, Expense>
     */
    private function expenses(Business $business, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Expense::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Per cashier: counted orders and sales, average ticket, and voids.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{name: string, orders: int, sales: float, average: float, voided: int, voided_amount: float, requested: int}>
     */
    private function cashiers(Collection $orders): Collection
    {
        return $orders
            ->groupBy('cashier_name')
            ->map(function (Collection $group, string $name): array {
                $counted = $this->counted($group);
                $voided = $group->where('status', OrderStatus::Voided);
                $sales = round($counted->sum(fn (Order $order) => (float) $order->subtotal), 2);

                return [
                    'name' => $name,
                    'orders' => $counted->count(),
                    'sales' => $sales,
                    'average' => $counted->count() > 0 ? round($sales / $counted->count(), 2) : 0.0,
                    'voided' => $voided->count(),
                    'voided_amount' => round($voided->sum(fn (Order $order) => (float) $order->subtotal), 2),
                    'requested' => $group->where('status', OrderStatus::VoidRequested)->count(),
                ];
            })
            ->sortByDesc('sales')
            ->values();
    }
}
