<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case Sale = 'sale';
    case Void = 'void';
    case Audit = 'audit';
    case Restock = 'restock';
    case Adjustment = 'adjustment';
}
