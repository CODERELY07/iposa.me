<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Equipment, bought in cash (terms = 1) or in monthly installments.
 */
#[Fillable(['business_id', 'name', 'vendor', 'price', 'installment_amount', 'terms', 'paid_count', 'first_due_on'])]
class Asset extends Model
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
            'price' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'terms' => 'integer',
            'paid_count' => 'integer',
            'first_due_on' => 'date',
        ];
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isFullyPaid(): bool
    {
        return $this->paid_count >= $this->terms;
    }

    /**
     * Amount of one payment: the installment, or the whole price for cash purchases.
     */
    public function paymentAmount(): float
    {
        return $this->installment_amount !== null ? (float) $this->installment_amount : (float) $this->price;
    }

    /**
     * Due date of the next unpaid installment, or null when fully paid.
     */
    public function nextDueOn(): ?Carbon
    {
        return $this->isFullyPaid() ? null : $this->first_due_on->copy()->addMonthsNoOverflow($this->paid_count);
    }
}
