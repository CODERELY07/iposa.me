<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One closing audit per business per day.
 */
#[Fillable(['business_id', 'date', 'user_id', 'counted_by', 'started_at', 'submitted_at', 'duration_seconds', 'last_movement_id', 'corrections_count', 'reopen_status', 'reopen_requested_by', 'reopen_requested_by_name', 'reopen_requested_at', 'reopen_decided_by', 'reopen_decided_by_name', 'reopen_decided_at'])]
class Audit extends Model
{
    use BelongsToBusiness;

    public const REOPEN_PENDING = 'pending';

    public const REOPEN_APPROVED = 'approved';

    public const REOPEN_DENIED = 'denied';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'duration_seconds' => 'integer',
            'corrections_count' => 'integer',
            'reopen_requested_at' => 'datetime',
            'reopen_decided_at' => 'datetime',
        ];
    }

    public function isReopenPending(): bool
    {
        return $this->reopen_status === self::REOPEN_PENDING;
    }

    public function isReopenApproved(): bool
    {
        return $this->reopen_status === self::REOPEN_APPROVED;
    }

    /**
     * @return HasMany<AuditLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(AuditLine::class);
    }

    /**
     * Peso value of bulk used, from the costs copied at count time.
     */
    public function usageCost(): float
    {
        return round($this->lines->sum(fn (AuditLine $line) => ((float) $line->used - (float) $line->recipe_surplus_costed) * (float) $line->unit_cost), 2);
    }
}
