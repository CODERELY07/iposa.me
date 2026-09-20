<?php

namespace App\Services\Platform;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removing a shop from the platform, in two deliberate steps.
 *
 * Trash is reversible: the shop and everything in it stays in the database,
 * but nobody there can sign in and it leaves every list and every metric.
 * Erasing is not reversible, and takes the shop's accounts with it.
 */
class BusinessTrashService
{
    public function moveToTrash(Business $business, ?string $reason): Business
    {
        DB::transaction(function () use ($business, $reason): void {
            $business->forceFill(['deletion_reason' => $reason])->save();
            $business->delete();
        });

        return $business;
    }

    public function restore(Business $business): Business
    {
        DB::transaction(function () use ($business): void {
            $business->restore();
            $business->forceFill(['deletion_reason' => null])->save();
        });

        return $business;
    }

    /**
     * What erasing this shop would take with it, for the confirmation dialog.
     *
     * @return array{users: int, orders: int, items: int, audits: int, expenses: int}
     */
    public function contents(Business $business): array
    {
        return [
            'users' => User::query()->where('business_id', $business->id)->count(),
            'orders' => $business->orders()->withoutGlobalScopes()->count(),
            'items' => $business->items()->withoutGlobalScopes()->count(),
            'audits' => $business->audits()->withoutGlobalScopes()->count(),
            'expenses' => $business->expenses()->withoutGlobalScopes()->count(),
        ];
    }

    /**
     * Erase the shop and everything belonging to it, including its accounts:
     * a user whose shop is gone can do nothing but take up an email address.
     *
     * Child rows go with it through the database's own cascades.
     *
     * @return array{users: int, orders: int, items: int, audits: int, expenses: int}
     *
     * @throws ValidationException
     */
    public function eraseForever(Business $business): array
    {
        if (! $business->trashed()) {
            throw ValidationException::withMessages([
                'business' => "{$business->business_name} has to be in the trash before it can be erased.",
            ]);
        }

        return DB::transaction(function () use ($business): array {
            $contents = $this->contents($business);
            $userIds = User::query()->where('business_id', $business->id)->pluck('id');

            // The shop goes first: `businesses.user_id` points at the owner, so the
            // accounts can only be removed once the row referring to them is gone.
            $business->forceDelete();
            User::query()->whereIn('id', $userIds)->delete();

            return $contents;
        });
    }
}
