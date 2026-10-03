<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogWasteRequest;
use App\Models\Item;
use App\Services\Inventory\WasteService;
use Illuminate\Http\RedirectResponse;

class ItemWasteController extends Controller
{
    /**
     * Take spilled or spoiled stock off the shelf with the reason. What it was worth counts
     * against profit as Waste (never as cash out).
     */
    public function __invoke(LogWasteRequest $request, Item $item, WasteService $waste): RedirectResponse
    {
        $variant = $request->variant();

        $result = $waste->log(
            $item,
            $request->user(),
            (float) $request->validated('quantity'),
            $request->container(),
            $request->validated('note'),
            $request->boughtOn(),
            $variant,
        );

        $item->refresh()->load('containers');
        $variant?->refresh();

        $name = $variant !== null ? "{$item->name} ({$variant->label})" : $item->name;
        $onHand = $variant !== null ? $variant->on_hand : $item->on_hand;
        $message = "Took {$item->describeQuantity($result['removed'])} off {$name} as waste. On hand: {$item->describeQuantity($onHand)}.";

        $message .= $result['priced']
            ? ' ₱'.number_format((float) $result['cost'], 2).' counted against profit as waste.'
            : " {$name} has no cost set, so this waste isn't counted in profit yet. Set its cost and log future waste again.";

        return redirect()->route('admin.inventory.edit', $item)->with('status', $message);
    }
}
