<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BusinessExports;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * CSV downloads. Always allowed, even when the trial ended: "your data is yours".
 */
class ExportController extends Controller
{
    /**
     * @var list<string>
     */
    public const DATASETS = ['ledger', 'expenses', 'menu', 'stock', 'orders', 'audits', 'all'];

    public function __invoke(Request $request, string $dataset, BusinessExports $exports): StreamedResponse|BinaryFileResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $business = $request->user()->business;
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->startOfMonth();
        $from = $from->max($to->subYears(2));

        if ($dataset === 'all') {
            return $this->zipEverything($business, $exports, $from->min($to->startOfYear()), $to);
        }

        $rows = $this->rows($dataset, $business, $exports, $from, $to);
        $filename = $this->filename($business, $dataset, $from, $to);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return iterable<int, list<string|int|float|null>>
     */
    private function rows(string $dataset, Business $business, BusinessExports $exports, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        return match ($dataset) {
            'ledger' => $exports->ledger($business, $from, $to),
            'expenses' => $exports->expenses($business, $from, $to),
            'menu' => $exports->menu($business),
            'stock' => $exports->stock($business),
            'orders' => $exports->orders($business, $from, $to),
            'audits' => $exports->audits($business, $from, $to),
        };
    }

    /**
     * One zip with every dataset, one CSV each.
     */
    private function zipEverything(Business $business, BusinessExports $exports, CarbonImmutable $from, CarbonImmutable $to): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'iposa-export-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach (array_diff(self::DATASETS, ['all']) as $dataset) {
            $handle = fopen('php://temp', 'w+');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($this->rows($dataset, $business, $exports, $from, $to) as $row) {
                fputcsv($handle, $row);
            }

            rewind($handle);
            $zip->addFromString($dataset.'.csv', stream_get_contents($handle));
            fclose($handle);
        }

        $zip->close();

        return response()
            ->download($path, Str::slug($business->business_name).'-iposa-'.$to->toDateString().'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    private function filename(Business $business, string $dataset, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $range = in_array($dataset, ['menu', 'stock'], true) ? $to->toDateString() : $from->toDateString().'-to-'.$to->toDateString();

        return Str::slug($business->business_name).'-'.$dataset.'-'.$range.'.csv';
    }
}
