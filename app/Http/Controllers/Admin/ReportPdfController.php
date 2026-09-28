<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRangeRequest;
use App\Reports\PeriodReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReportPdfController extends Controller
{
    /**
     * The owner's business report for the chosen period, as a PDF download.
     */
    public function __invoke(ReportRangeRequest $request, PeriodReport $report): Response
    {
        [$from, $to] = $request->range();
        $business = $request->user()->business;

        $pdf = Pdf::loadView('reports.pdf', $report->build($business, $from, $to))->setPaper('a4');

        // "Page 2 of 9" in the footer: the page count is only known after rendering.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(505, 810, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.48, 0.51, 0.56]);

        return $pdf->download(Str::slug($business->business_name).'-report-'.$from->toDateString().'-to-'.$to->toDateString().'.pdf');
    }
}
