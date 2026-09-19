<?php

namespace App\Enums;

enum ExpenseKind: string
{
    case Fixed = 'fixed';
    case Variable = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Variable => 'Variable',
        };
    }
}
