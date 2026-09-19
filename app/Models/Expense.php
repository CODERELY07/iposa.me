<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'date', 'category', 'description', 'kind', 'amount', 'asset_id', 'user_id', 'logged_by'])]
class Expense extends Model
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
            'category' => ExpenseCategory::class,
            'kind' => ExpenseKind::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
