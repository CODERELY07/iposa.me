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
     * Categories an owner can pick in the quick-add row. Payables come from equipment installments.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $category) => $category !== self::Payables));
    }
}
