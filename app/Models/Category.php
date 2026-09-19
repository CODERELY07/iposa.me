<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'name', 'color', 'sort'])]
class Category extends Model
{
    use BelongsToBusiness;

    /**
     * Tile colors the register can show. Keys are stored in `categories.color`.
     *
     * @var list<string>
     */
    public const COLORS = ['brand', 'rose', 'yellow', 'sky', 'emerald', 'violet', 'ink'];

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
