<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRangeRequest;
use App\Models\CapitalContribution;
use App\Models\Delivery;
use App\Reports\DailyLedger;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Profit & ledger for a week, a month, or a custom range (max one year).
     */
    public function __invoke(ReportRangeRequest $request, DailyLedger $ledger): View
    {
        $period = $request->period();
        [$from, $to] = $request->range();

        $business = $request->user()->business;
        $rows = $ledger->forRange($business, $from, $to);
        $totals = $ledger->totals($rows);

        // All-time, not scoped to the chosen period: it's a running equity balance, not a flow.
        $capital = CapitalContribution::query()->latest('date')->latest('id')->get();

        return view('admin.reports', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'ledger' => $rows->reverse()->values(),
            'totals' => $totals,
            'dayCount' => $rows->count(),
            'bestSellers' => $ledger->bestSellers($business, $from, $to),
            'uncheckedDeliveries' => Delivery::query()->where('status', Delivery::PENDING)->count(),
            'capital' => $capital,
            'totalCapital' => round((float) $capital->sum('amount'), 2),
        ]);
    }
}
