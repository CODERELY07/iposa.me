<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Utilities = 'utilities';
    case Rent = 'rent';
    case Wages = 'wages';
    case Supplies = 'supplies';
    case StockPurchase = 'stock_purchase';
    case Payables = 'payables';
    case MissingStock = 'missing_stock';
    case Misc = 'misc';

    public function label(): string
    {
        return match ($this) {
            self::Utilities => 'Utilities',
            self::Rent => 'Rent',
            self::Wages => 'Wages',
            self::Supplies => 'Supplies',
            self::StockPurchase => 'Stock purchase',
            self::Payables => 'Equipment payables',
            self::MissingStock => 'Missing stock',
            self::Misc => 'Misc',
        };
    }

    /**
     * Kind suggested when the owner doesn't pick one.
     */
    public function defaultKind(): ExpenseKind
    {
        return match ($this) {
            self::Utilities, self::Rent, self::Wages, self::Payables => ExpenseKind::Fixed,
            default => ExpenseKind::Variable,
        };
    }

    /**
     * Whether this spending lowers profit on the day it is logged.
     *
     * Stock bought for the shelf does not: it lowers profit later, when it is used,
     * through each sale's cost and the closing count. Subtracting the purchase too
     * would count the same buns twice.
     */
    public function lowersProfit(): bool
    {
        return $this !== self::StockPurchase;
    }

    /**
     * Categories an owner can pick in the quick-add row. Payables come from equipment
     * installments and missing stock from checked deliveries.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $category) => ! in_array($category, [self::Payables, self::MissingStock], true)));
    }
}
