<?php

namespace App\Enums;

enum SubscriptionPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Rejected => 'Rejected',
        };
    }
}
