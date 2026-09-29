<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One cash drawer reconciliation per business per day: what the owner put in to
 * start, and what was actually counted, so the shop can see the drawer is right.
 */
#[Fillable(['business_id', 'date', 'starting_amount', 'counted_amount', 'started_by', 'started_by_name', 'counted_by', 'counted_by_name', 'counted_at'])]
class CashFloat extends Model
{
    use BelongsToBusiness;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starting_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
            'counted_at' => 'datetime',
        ];
    }
}
