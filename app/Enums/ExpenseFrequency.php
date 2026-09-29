<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum ExpenseFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * The next occurrence after this one. Monthly and yearly keep the same day
     * where possible; the 31st of a short month lands on its last day instead
     * of rolling into the next one.
     */
    public function next(CarbonInterface $from): CarbonInterface
    {
        return match ($this) {
            self::Daily => $from->copy()->addDay(),
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonthNoOverflow(),
            self::Yearly => $from->copy()->addYearNoOverflow(),
        };
    }
}
