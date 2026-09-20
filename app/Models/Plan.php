<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan, managed by the platform operator.
 *
 * `key` is what `businesses.plan` stores and what the `plan:` middleware reads,
 * so it never changes once the plan exists.
 */
#[Fillable(['key', 'name', 'price', 'pitch', 'staff_limit', 'features', 'feature_list', 'sort'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * The modules a plan can switch on. The keys are used by `plan:<feature>`
     * middleware and `Business::hasFeature()`, so they are fixed in code.
     *
     * @var array<string, string>
     */
    public const FEATURES = [
        'expenses' => 'Expenses & equipment payables',
        'reports' => 'Profit & ledger (P&L)',
        'recipes' => 'Ingredient links (recipes)',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'staff_limit' => 'integer',
            'sort' => 'integer',
            'features' => 'array',
            'feature_list' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Businesses currently on this plan.
     *
     * @return HasMany<Business, $this>
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'plan', 'key');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public function subscriberCount(): int
    {
        return $this->businesses()->count();
    }

    /**
     * The shape every screen reads (the same keys the old config array had).
     *
     * @return array{key: string, name: string, price: float, pitch: ?string, staff_limit: ?int, features: array<string, bool>, feature_list: list<string>, archived: bool}
     */
    public function details(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'price' => (float) $this->price,
            'pitch' => $this->pitch,
            'staff_limit' => $this->staff_limit,
            'features' => $this->features ?? [],
            'feature_list' => $this->feature_list ?? [],
            'archived' => $this->isArchived(),
        ];
    }

    /**
     * Plans an owner can switch to, cheapest first.
     *
     * @return Collection<int, self>
     */
    public static function selectable(): Collection
    {
        return self::query()->available()->orderBy('sort')->orderBy('price')->get();
    }

    /**
     * The plan new sign-ups start on: the configured default, or the cheapest available.
     */
    public static function default(): ?self
    {
        return self::query()->available()->firstWhere('key', config('plans.default'))
            ?? self::query()->available()->orderBy('price')->first();
    }

    /**
     * Look up a plan by the key stored on a business.
     */
    public static function findByKey(?string $key): ?self
    {
        if ($key === null) {
            return null;
        }

        return self::query()->firstWhere('key', $key);
    }
}
