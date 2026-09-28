<?php

namespace App\Reports;

use Illuminate\Support\Collection;

/**
 * Sales and profit side by side per day (or week), drawn as SVG for the PDF report.
 * One zero line; a loss day's profit bar drops below it in the loss color.
 */
class TrendChart
{
    public const SALES = '#3f76c4';

    public const PROFIT = '#d97706';

    public const LOSS = '#b42318';

    private const WIDTH = 720;

    private const HEIGHT = 200;

    private const LEFT = 44;

    private const RIGHT = 6;

    private const TOP = 8;

    private const BOTTOM = 22;

    /**
     * @param  Collection<int, array{label: string, sales: float, net: float}>  $points
     */
    public static function dataUri(Collection $points): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($points));
    }

    /**
     * @param  Collection<int, array{label: string, sales: float, net: float}>  $points
     */
    public static function svg(Collection $points): string
    {
        $count = max(1, $points->count());
        $max = max(1.0, (float) $points->max('sales'), (float) $points->max('net'));
        $min = min(0.0, (float) $points->min('net'));
        $step = self::niceStep(($max - $min) / 4);
        $max = ceil($max / $step) * $step;
        $min = floor($min / $step) * $step;

        $plotWidth = self::WIDTH - self::LEFT - self::RIGHT;
        $plotHeight = self::HEIGHT - self::TOP - self::BOTTOM;
        $y = fn (float $value): float => round(self::TOP + ($max - $value) / ($max - $min) * $plotHeight, 2);
        $slot = $plotWidth / $count;
        $bar = max(1.0, min(14.0, ($slot - 3) / 2));
        $labelEvery = (int) max(1, ceil($count / 16));

        $parts = [];

        for ($tick = $min; $tick <= $max + 0.001; $tick += $step) {
            $ty = $y($tick);
            $parts[] = sprintf('<line x1="%d" y1="%s" x2="%d" y2="%s" stroke="%s" stroke-width="%s"/>', self::LEFT, $ty, self::WIDTH - self::RIGHT, $ty, abs($tick) < 0.001 ? '#1f2328' : '#e6e8eb', abs($tick) < 0.001 ? '1' : '0.6');
            $parts[] = sprintf('<text x="%d" y="%s" font-size="9" fill="#5b6470" text-anchor="end">%s</text>', self::LEFT - 5, $ty + 3, self::shortAmount($tick));
        }

        foreach ($points->values() as $index => $point) {
            $x = self::LEFT + $index * $slot + ($slot - 2 * $bar - 2) / 2;
            $sales = (float) $point['sales'];
            $net = (float) $point['net'];

            if ($sales > 0) {
                $parts[] = self::rect($x, $y($sales), $bar, $y(0) - $y($sales), self::SALES);
            }

            if (abs($net) > 0.004) {
                $top = $y(max($net, 0));
                $parts[] = self::rect($x + $bar + 2, $top, $bar, $y(min($net, 0)) - $top, $net < 0 ? self::LOSS : self::PROFIT);
            }

            if ($index % $labelEvery === 0) {
                $parts[] = sprintf('<text x="%s" y="%d" font-size="9" fill="#5b6470" text-anchor="middle">%s</text>', round(self::LEFT + $index * $slot + $slot / 2, 2), self::HEIGHT - 6, e($point['label']));
            }
        }

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %1$d %2$d">%s</svg>', self::WIDTH, self::HEIGHT, implode('', $parts));
    }

    private static function rect(float $x, float $y, float $width, float $height, string $color): string
    {
        return sprintf('<rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>', round($x, 2), round($y, 2), round($width, 2), round(max(0.8, $height), 2), $color);
    }

    /**
     * A round gridline step: 1, 2 or 5 times a power of ten.
     */
    private static function niceStep(float $raw): float
    {
        $raw = max($raw, 1.0);
        $power = 10 ** floor(log10($raw));

        foreach ([1, 2, 5, 10] as $multiple) {
            if ($raw <= $multiple * $power) {
                return $multiple * $power;
            }
        }

        return 10 * $power;
    }

    /**
     * 12500 → "12.5k", 800 → "800", −2000 → "−2k".
     */
    private static function shortAmount(float $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $value = abs($value);

        return $sign.($value >= 1000 ? rtrim(rtrim(number_format($value / 1000, 1), '0'), '.').'k' : number_format($value));
    }
}
