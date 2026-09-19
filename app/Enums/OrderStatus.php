<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Paid = 'paid';
    case VoidRequested = 'void_requested';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::VoidRequested => 'Void requested',
            self::Voided => 'Voided',
        };
    }

    /**
     * Orders that still count as sales in every report.
     *
     * @return list<self>
     */
    public static function countedAsSales(): array
    {
        return [self::Paid, self::VoidRequested];
    }
}
