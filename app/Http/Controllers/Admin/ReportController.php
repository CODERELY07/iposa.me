<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\DailyLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Profit & ledger for a week, a month, or a custom range (max one year).
     */
    public function __invoke(Request $request, DailyLedger $ledger): View
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:week,month,custom'],
            'from' => ['nullable', 'required_if:period,custom', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        $period = $validated['period'] ?? 'month';
        [$from, $to] = $this->range($period, $validated['from'] ?? null, $validated['to'] ?? null);

        $business = $request->user()->business;
        $rows = $ledger->forRange($business, $from, $to);
        $totals = $ledger->totals($rows);

        return view('admin.reports', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'ledger' => $rows->reverse()->values(),
            'totals' => $totals,
            'dayCount' => $rows->count(),
            'bestSellers' => $ledger->bestSellers($business, $from, $to),
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(string $period, ?string $from, ?string $to): array
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            'week' => [$today->subDays(6), $today],
            'custom' => $this->customRange(CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
            default => [$today->startOfMonth(), $today],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->max($to->subYear()), $to];
    }
}
