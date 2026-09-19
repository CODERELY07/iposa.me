<?php

namespace App\Enums;

enum ItemKind: string
{
    /** Sold on the register. */
    case Menu = 'menu';

    /** Counted by the piece and deducted through recipe links. */
    case Piece = 'piece';

    /** Counted by eye in the closing audit (bottles, tubs, tanks). */
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Menu => 'Menu item',
            self::Piece => 'Piece',
            self::Bulk => 'Bulk & liquid',
        };
    }
}
