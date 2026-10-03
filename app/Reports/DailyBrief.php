<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The owner's end-of-day text: voids and stock, nothing else. Plain ASCII and a few
 * lines, so it fits one or two SMS parts (the peso sign would force the shorter,
 * 70-character parts, so amounts are written "PHP 340").
 */
class DailyBrief
{
    /** Keep the whole text inside three 153-character SMS parts. */
    private const MAX_LENGTH = 459;

    public function __construct(private StockAlerts $stockAlerts) {}

    public function build(Business $business, CarbonInterface $day): string
    {
        $lines = [
            $business->business_name.' '.$day->format('j M'),
            $this->voidLine($business, $day),
        ];

        $stock = $this->stockLine($business, $lines);

        return Str::ascii(implode("\n", [...$lines, $stock]));
    }

    private function voidLine(Business $business, CarbonInterface $day): string
    {
        $voided = Order::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('status', OrderStatus::Voided)
            ->whereBetween('voided_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->get(['id', 'subtotal', 'void_requested_by', 'voided_by']);

        $waiting = Order::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('status', OrderStatus::VoidRequested)
            ->count();

        $text = 'VOIDS: ';

        if ($voided->isEmpty()) {
            $text .= 'none.';
        } else {
            // Whoever asked for the void, or whoever did it when nobody had to ask.
            $names = User::query()
                ->whereIn('id', $voided->map(fn (Order $order) => $order->void_requested_by ?? $order->voided_by)->filter()->unique())
                ->pluck('name', 'id');

            $byPerson = $voided
                ->groupBy(fn (Order $order) => $names[$order->void_requested_by ?? $order->voided_by] ?? 'Owner')
                ->map(fn ($group, $name) => $name.' '.$group->count())
                ->values()
                ->join(', ');

            $text .= $voided->count().' = PHP '.Item::trimNumber((float) $voided->sum('subtotal')).' ('.$byPerson.').';
        }

        if ($waiting > 0) {
            $text .= ' '.$waiting.' void '.Str::plural('request', $waiting).' waiting.';
        }

        return $text;
    }

    /**
     * @param  list<string>  $before  the lines already written, to measure what room is left
     */
    private function stockLine(Business $business, array $before): string
    {
        $low = $this->stockAlerts->low($business, 8);

        if ($low->isEmpty()) {
            return 'STOCK: nothing low.';
        }

        $room = self::MAX_LENGTH - strlen(implode("\n", $before)) - 1;
        $text = 'LOW STOCK: ';
        $shown = 0;

        foreach ($low as $row) {
            $entry = $row['name'].' '.$row['left'].($row['runsOut'] === 'today at this pace' ? ' (out today)' : '');
            $candidate = $text.($shown > 0 ? '; ' : '').$entry;

            if (strlen(Str::ascii($candidate)) > $room - 14) {
                break;
            }

            $text = $candidate;
            $shown++;
        }

        $more = $low->count() - $shown;

        return $text.($more > 0 ? ' +'.$more.' more' : '').'.';
    }
}
