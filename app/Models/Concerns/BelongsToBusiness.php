<?php

namespace App\Models\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping: a logged-in owner or cashier only ever reads and writes rows of their own business.
 *
 * The scope is skipped when nobody is logged in (console, queue, seeders) and for the platform
 * operator, who works across businesses on purpose.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query): void {
            $businessId = static::currentBusinessId();

            if ($businessId !== null) {
                $query->where($query->qualifyColumn('business_id'), $businessId);
            }
        });

        static::creating(function (self $model): void {
            if (blank($model->business_id)) {
                $model->business_id = static::currentBusinessId();
            }
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The business of the logged-in shop user, or null for guests and the platform operator.
     */
    protected static function currentBusinessId(): ?int
    {
        $user = auth()->user();

        if ($user === null || $user->isSuperAdmin()) {
            return null;
        }

        return $user->business_id;
    }
}
