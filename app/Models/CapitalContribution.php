<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Money the owner put into the business — startup capital, or a later top-up.
 * Equity, not an expense: never counted against Net profit or Cash-basis profit,
 * and never reduced by restocking or day-to-day spending.
 */
#[Fillable(['business_id', 'date', 'amount', 'note', 'user_id', 'logged_by'])]
class CapitalContribution extends Model
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
            'amount' => 'decimal:2',
        ];
    }
}
