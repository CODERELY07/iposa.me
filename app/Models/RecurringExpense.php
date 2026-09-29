<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseFrequency;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A bill that repeats: rent, wages, a subscription. Never posts an expense on its
 * own — it just remembers when the next one is due, so the owner gets a one-tap
 * reminder instead of a silent, unreviewed entry in their profit and loss.
 */
#[Fillable(['business_id', 'category', 'description', 'amount', 'frequency', 'next_due_on', 'created_by', 'created_by_name'])]
class RecurringExpense extends Model
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
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'frequency' => ExpenseFrequency::class,
            'next_due_on' => 'date',
        ];
    }

    public function isDue(): bool
    {
        return $this->next_due_on->lte(today());
    }
}
