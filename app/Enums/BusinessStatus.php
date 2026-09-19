<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';

    /**
     * Human-readable label for tables and badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Suspended => 'Suspended',
        };
    }
}
