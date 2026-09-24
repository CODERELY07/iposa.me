<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to what a sale of a menu item uses.
 *
 * Owner saves are recorded as "saved". A cashier's change is "pending" until the owner
 * approves or rejects it; a newer request for the same item marks the older one "replaced".
 *
 * `before` and `after` are snapshots: list<array{piece_item_id: int, piece: string, unit: string, qty: float, item_variant_id: ?int, variant: ?string}>
 */
#[Fillable(['business_id', 'item_id', 'user_id', 'requested_by', 'status', 'before', 'after', 'decided_by', 'decided_by_name', 'decided_at'])]
class RecipeChange extends Model
{
    use BelongsToBusiness;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const REPLACED = 'replaced';

    public const SAVED = 'saved';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Waiting for approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::REPLACED => 'Replaced by a newer request',
            default => 'Saved',
        };
    }

    /**
     * What changed, one line per link: "Add 15 ml Ketchup", "Remove 1 pc Burger bun",
     * "Beef patty: 1 → 2 pc".
     *
     * @return list<string>
     */
    public function summary(): array
    {
        $before = collect($this->before)->keyBy(fn (array $line) => self::lineKey($line));
        $after = collect($this->after)->keyBy(fn (array $line) => self::lineKey($line));
        $changes = [];

        foreach ($after as $key => $line) {
            $old = $before->get($key);

            if ($old === null) {
                $changes[] = 'Add '.self::describe($line);
            } elseif (abs((float) $old['qty'] - (float) $line['qty']) >= 0.0005) {
                $changes[] = self::name($line).': '.Item::trimNumber((float) $old['qty'], 3).' → '.Item::trimNumber((float) $line['qty'], 3).' '.$line['unit'];
            }
        }

        foreach ($before as $key => $line) {
            if (! $after->has($key)) {
                $changes[] = 'Remove '.self::describe($line);
            }
        }

        return $changes;
    }

    /**
     * Two snapshots mean the same links (order and names aside).
     *
     * @param  list<array<string, mixed>>  $first
     * @param  list<array<string, mixed>>  $second
     */
    public static function sameLinks(array $first, array $second): bool
    {
        $normalize = fn (array $lines) => collect($lines)
            ->map(fn (array $line) => self::lineKey($line).'='.number_format((float) $line['qty'], 3, '.', ''))
            ->sort()
            ->values()
            ->all();

        return $normalize($first) === $normalize($second);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function lineKey(array $line): string
    {
        return $line['piece_item_id'].'|'.($line['item_variant_id'] ?? 'all');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function describe(array $line): string
    {
        return trim(Item::trimNumber((float) $line['qty'], 3).' '.$line['unit']).' '.self::name($line);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function name(array $line): string
    {
        return $line['piece'].($line['variant'] ? ' ('.$line['variant'].')' : '');
    }
}
