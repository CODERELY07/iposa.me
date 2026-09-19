<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\PaymentMethod;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

#[Fillable([
    'business_type', 'business_name', 'user_id', 'status', 'start_date', 'due_date',
    'plan', 'address', 'tin', 'receipt_footer', 'settings', 'suspended_at', 'suspension_reason',
])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * Settings every business starts with. Stored values are merged on top.
     *
     * @var array{payment_methods: list<string>, audit_reminder_time: string, default_low_threshold: int, cashier_permissions: array<string, bool>}
     */
    public const DEFAULT_SETTINGS = [
        'payment_methods' => ['cash', 'gcash', 'maya'],
        'audit_reminder_time' => '21:30',
        'default_low_threshold' => 10,
        'cashier_permissions' => [
            'run_audit' => true,
            'view_costs' => false,
            'void_orders' => false,
            'log_expenses' => true,
        ],
    ];

    /**
     * Human labels for the cashier permission switches.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const CASHIER_PERMISSIONS = [
        'run_audit' => ['label' => 'Run the closing audit', 'hint' => 'Recommended. Whoever closes, counts.'],
        'view_costs' => ['label' => 'See cost prices and margins', 'hint' => 'Off keeps your margins private.'],
        'void_orders' => ['label' => 'Void a paid order', 'hint' => 'Off sends a void request to you instead.'],
        'log_expenses' => ['label' => 'Log expenses', 'hint' => 'For ice, LPG and small cash buys.'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'start_date' => 'datetime',
            'due_date' => 'datetime',
            'suspended_at' => 'datetime',
            'settings' => 'array',
            'last_order_number' => 'integer',
        ];
    }

    /**
     * The user who signed the business up.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Everyone who works here: the owner and cashiers.
     *
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Audit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Match business name, type, or the owner's name or email.
     *
     * @param  Builder<Business>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('business_name', 'like', $like)
                ->orWhere('business_type', 'like', $like)
                ->orWhereHas('owner', fn (Builder $owner) => $owner
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like));
        });
    }

    /**
     * True when the due date has passed.
     */
    public function isOverdue(): bool
    {
        return $this->due_date?->isPast() ?? false;
    }

    public function isSuspended(): bool
    {
        return $this->status === BusinessStatus::Suspended;
    }

    /**
     * Trial ended or payment overdue: only billing and exports stay open.
     */
    public function requiresPayment(): bool
    {
        if ($this->status === BusinessStatus::PastDue) {
            return true;
        }

        return in_array($this->status, [BusinessStatus::Trial, BusinessStatus::Active], true) && $this->isOverdue();
    }

    /**
     * Whole days until the due date (negative when overdue), or null without a due date.
     */
    public function daysUntilDue(): ?int
    {
        if ($this->due_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date->copy()->startOfDay(), false);
    }

    /**
     * The plan's config block from config/plans.php.
     *
     * @return array{name: string, price: int, pitch: string, staff_limit: ?int, features: array<string, bool>, feature_list: list<string>}
     */
    public function planDetails(): array
    {
        return config('plans.plans.'.$this->plan) ?? config('plans.plans.'.config('plans.default'));
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->planDetails()['features'][$feature] ?? false);
    }

    /**
     * Staff seats left on the plan, or null when unlimited.
     */
    public function staffSeatsLeft(): ?int
    {
        $limit = $this->planDetails()['staff_limit'];

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->members()->where('role', User::ROLE_STAFF)->count());
    }

    /**
     * A setting with the default filled in, using dot notation (e.g. "cashier_permissions.void_orders").
     */
    public function setting(string $key): mixed
    {
        return Arr::get($this->resolvedSettings(), $key);
    }

    /**
     * All settings with defaults filled in.
     *
     * @return array<string, mixed>
     */
    public function resolvedSettings(): array
    {
        $resolved = self::DEFAULT_SETTINGS;

        foreach ($this->settings ?? [] as $key => $value) {
            // Permissions merge key by key; lists like payment_methods replace the default outright.
            $resolved[$key] = $key === 'cashier_permissions' && is_array($value)
                ? array_replace(self::DEFAULT_SETTINGS['cashier_permissions'], $value)
                : $value;
        }

        return $resolved;
    }

    /**
     * Can cashiers of this business do this? Owners can always do everything.
     */
    public function cashierCan(string $permission): bool
    {
        return (bool) $this->setting('cashier_permissions.'.$permission);
    }

    /**
     * Payment methods switched on for the register.
     *
     * @return list<PaymentMethod>
     */
    public function enabledPaymentMethods(): array
    {
        return array_values(array_filter(array_map(
            fn (string $method) => PaymentMethod::tryFrom($method),
            $this->setting('payment_methods') ?? [],
        )));
    }

    public function lowStockThreshold(): float
    {
        return (float) $this->setting('default_low_threshold');
    }
}
