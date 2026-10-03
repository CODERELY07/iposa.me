<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where an owner buys stock. Just a name, remembered so the same supplier is picked next time.
 */
#[Fillable(['business_id', 'name'])]
class Supplier extends Model
{
    use BelongsToBusiness;

    /**
     * @return HasMany<ItemPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(ItemPurchase::class);
    }
}
