<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id', 'number', 'uuid', 'user_id', 'cashier_name', 'payment_method', 'subtotal', 'tendered', 'change',
    'status', 'paid_at', 'void_requested_by', 'voided_by', 'voided_at',
])]
class Order extends Model
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
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Orders that count as sales (paid, or awaiting a void decision).
     *
     * @param  Builder<Order>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::countedAsSales());
    }

    public function isVoided(): bool
    {
        return $this->status === OrderStatus::Voided;
    }
}
