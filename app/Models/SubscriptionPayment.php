<?php

namespace App\Models;

use App\Enums\SubscriptionPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual payment (GCash / bank reference) an owner submits and the operator confirms.
 */
#[Fillable(['business_id', 'plan', 'amount', 'method', 'reference', 'status', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note'])]
class SubscriptionPayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => SubscriptionPaymentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
