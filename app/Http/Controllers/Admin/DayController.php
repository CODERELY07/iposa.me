<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\DailyLedger;
use App\Reports\DayBreakdown;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The details behind each number on the Today page: sales, ingredients, bulk used, expenses.
 */
class DayController extends Controller
{
    public const SECTIONS = ['sales', 'ingredients', 'bulk', 'expenses'];

    public function __invoke(Request $request, string $section, DailyLedger $ledger, DayBreakdown $breakdown): View
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        $business = $request->user()->business;
        $day = $this->day($request);

        return view('admin.day.'.$section, [
            'business' => $business,
            'section' => $section,
            'day' => $day,
            'ledgerDay' => $ledger->forRange($business, $day, $day)->first(),
            'data' => $breakdown->{$section}($business, $day),
        ]);
    }

    /**
     * The day asked for (?date=2026-09-20), never in the future; today otherwise.
     */
    private function day(Request $request): CarbonImmutable
    {
        $date = $request->query('date');

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date);

            if ($parsed !== null && $parsed->format('Y-m-d') === $date && ! $parsed->isAfter(today())) {
                return $parsed->startOfDay();
            }
        }

        return CarbonImmutable::today();
    }
}
