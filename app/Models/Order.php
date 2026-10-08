<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id', 'number', 'uuid', 'user_id', 'cashier_name', 'payment_method', 'order_type', 'subtotal', 'tendered', 'change',
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
            'order_type' => OrderType::class,
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

    /**
     * Plain data for printing this order's receipt, here or on the Bluetooth printer.
     * Needs the lines loaded.
     *
     * @return array<string, mixed>
     */
    public function receiptData(Business $business): array
    {
        return [
            ...$business->receiptHeader(),
            'number' => $this->number,
            'offline' => false,
            'paidAt' => $this->paid_at->format('M j, Y g:i A'),
            'cashierName' => $this->cashier_name,
            'voided' => $this->isVoided(),
            'paymentLabel' => $this->payment_method->label(),
            'subtotal' => (float) $this->subtotal,
            'tendered' => (float) ($this->tendered ?? $this->subtotal),
            'change' => $this->change !== null ? (float) $this->change : null,
            'lines' => $this->lines->map(fn (OrderLine $line) => [
                'name' => $line->name,
                'variantLabel' => $line->variant_label,
                'qty' => $line->qty,
                'price' => (float) $line->price,
                'total' => $line->lineTotal(),
            ])->values()->all(),
        ];
    }
}
