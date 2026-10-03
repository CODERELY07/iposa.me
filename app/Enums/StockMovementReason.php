<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case Sale = 'sale';
    case Void = 'void';
    case Audit = 'audit';
    case Restock = 'restock';
    case Adjustment = 'adjustment';
    case Waste = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::Void => 'Void',
            self::Audit => 'Closing audit',
            self::Restock => 'Restock',
            self::Adjustment => 'Adjustment',
            self::Waste => 'Waste',
        };
    }
}
