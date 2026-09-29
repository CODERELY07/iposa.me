<?php

namespace App\Enums;

/**
 * How a menu item's cost of goods sold is worked out. Inventory deduction
 * never depends on this — it only decides what counts toward COGS.
 */
enum CostingMethod: string
{
    /** Only the cost the owner typed. Linked pieces & liquids still leave the shelf. */
    case ManualOnly = 'manual_only';

    /** Only what the linked pieces & liquids cost at today's prices. Any typed cost is ignored. */
    case LinkedOnly = 'linked_only';

    /** The typed cost plus what the linked pieces & liquids cost. */
    case ManualPlusLinked = 'manual_plus_linked';

    public function label(): string
    {
        return match ($this) {
            self::ManualOnly => 'Manual cost only',
            self::LinkedOnly => 'Linked pieces & liquids only',
            self::ManualPlusLinked => 'Manual cost + linked pieces & liquids',
        };
    }

    public function usesManual(): bool
    {
        return $this !== self::LinkedOnly;
    }

    public function usesLinked(): bool
    {
        return $this !== self::ManualOnly;
    }
}
