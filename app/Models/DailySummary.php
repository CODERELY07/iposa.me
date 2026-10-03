<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The text an owner got for one day: the voids and the stock running low.
 */
#[Fillable(['business_id', 'date', 'body', 'status', 'attempts', 'error', 'sent_at'])]
class DailySummary extends Model
{
    use BelongsToBusiness;

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** Tries before giving up for the day. */
    public const MAX_ATTEMPTS = 3;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function wasSent(): bool
    {
        return $this->status === self::SENT;
    }
}
