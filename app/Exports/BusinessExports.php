<?php

namespace App\Exports;

use App\Enums\ItemKind;
use App\Models\Audit;
use App\Models\AuditLine;
use App\Models\Business;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Order;
use App\Reports\DailyLedger;
use Carbon\CarbonInterface;

/**
 * CSV datasets an owner can download. Each returns a header row plus data rows.
 * Opens cleanly in Excel and Google Sheets (UTF-8 with BOM is added by the controller).
 */
class BusinessExports
{
    public function __construct(private DailyLedger $ledger) {}

    /**
     * @return iterable<int, list<string|int|float|null>>
     */
    public function ledger(Business $business, CarbonInterface $from, CarbonInterface $to): iterable
    {
        yield ['Date', 'Orders', 'Sales', 'Ingredients (COGS)', 'Bulk used', 'Expenses', 'Net', 'Margin %', 'Stock bought (not in net)'];

        foreach ($this->ledger->forRange($business, $from, $to) as $row) {
            yield [
                $row['date']->toDateString(), $row['orders'], $row['sales'], $row['cogs'], $row['bulk'], $row['expenses'], $row['net'],
                $row['sales'] > 0 ? round($row['net'] / $row['sales'] * 100, 1) : null,
                $row['stock_purchases'],
            ];
        }
    }

    /**
     * @return iterable<int, list<string|int|float|null>>
     */
    public function expenses(Business $business, CarbonInterface $from, CarbonInterface $to): iterable
    {
        yield ['Date', 'Category', 'Description', 'Type', 'Amount', 'Logged by'];

        $expenses = Expense::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->orderBy('date')
            ->cursor();

        foreach ($expenses as $expense) {
            yield [$expense->date->toDateString(), $expense->category->label(), $expense->description, $expense->kind->label(), (float) $expense->amount, $expense->logged_by];
        }
    }

    /**
     * The pricing matrix, same columns the importer reads (name, category, size, cost, price).
     * Cost is what the owner typed, so a re-import never adds the links twice;
     * profit and margin include linked pieces for items that ask for it.
     *
     * @return iterable<int, list<string|int|float|null>>
     */
    public function menu(Business $business): iterable
    {
        yield ['name', 'category', 'size', 'cost', 'price', 'profit', 'margin %'];

        $items = Item::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('kind', ItemKind::Menu)
            ->whereNull('archived_at')
            ->with(['category', 'variants', 'recipeLines.piece'])
            ->orderBy('name')
            ->get();

        foreach ($items as $item) {
            foreach ($item->variants as $variant) {
                /** @var ItemVariant $variant */
                yield [$item->name, $item->category?->name, $variant->label, (float) $variant->cost, (float) $variant->price, $variant->profit(), $variant->marginPercent()];
            }
        }
    }

    /**
     * @return iterable<int, list<string|int|float|null>>
     */
    public function stock(Business $business): iterable
    {
        yield ['Item', 'Kind', 'Unit', 'On hand', 'Alert at', 'Unit cost', 'Stock value'];

        $items = Item::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereNull('archived_at')
            ->whereNotNull('on_hand')
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        foreach ($items as $item) {
            yield [
                $item->name, $item->kind->label(), $item->unit, (float) $item->on_hand, $item->low_threshold !== null ? (float) $item->low_threshold : null,
                $item->unit_cost !== null ? (float) $item->unit_cost : null,
                $item->unit_cost !== null ? round((float) $item->on_hand * (float) $item->unit_cost, 2) : null,
            ];
        }
    }

    /**
     * @return iterable<int, list<string|int|float|null>>
     */
    public function orders(Business $business, CarbonInterface $from, CarbonInterface $to): iterable
    {
        yield ['Order #', 'Paid at', 'Cashier', 'Payment', 'Status', 'Item', 'Size', 'Qty', 'Price', 'Line total'];

        $orders = Order::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('lines')
            ->orderBy('paid_at')
            ->lazy(200);

        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                yield [
                    $order->number, $order->paid_at->format('Y-m-d H:i'), $order->cashier_name, $order->payment_method->label(), $order->status->label(),
                    $line->name, $line->variant_label, $line->qty, (float) $line->price, $line->lineTotal(),
                ];
            }
        }
    }

    /**
     * @return iterable<int, list<string|int|float|null>>
     */
    public function audits(Business $business, CarbonInterface $from, CarbonInterface $to): iterable
    {
        yield ['Date', 'Counted by', 'Item', 'Expected', 'Counted', 'Used', 'Restocked', 'Unit cost', 'Used value'];

        $audits = Audit::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->with('lines.item')
            ->orderBy('date')
            ->get();

        foreach ($audits as $audit) {
            foreach ($audit->lines as $line) {
                /** @var AuditLine $line */
                yield [
                    $audit->date->toDateString(), $audit->counted_by, $line->item?->name, (float) $line->expected, (float) $line->counted,
                    (float) $line->used, (float) $line->restocked, (float) $line->unit_cost, round((float) $line->used * (float) $line->unit_cost, 2),
                ];
            }
        }
    }
}
