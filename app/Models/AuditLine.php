<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['audit_id', 'item_id', 'expected', 'counted', 'used', 'restocked', 'recipe_surplus', 'recipe_deducted', 'recipe_deducted_costed', 'recipe_surplus_costed', 'recipe_fix', 'recipe_fix_at', 'unit_cost'])]
class AuditLine extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected' => 'decimal:3',
            'counted' => 'decimal:3',
            'used' => 'decimal:3',
            'restocked' => 'decimal:3',
            'recipe_surplus' => 'decimal:3',
            'recipe_deducted' => 'decimal:3',
            'recipe_deducted_costed' => 'decimal:3',
            'recipe_surplus_costed' => 'decimal:3',
            'recipe_fix_at' => 'datetime',
            'unit_cost' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<Audit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
