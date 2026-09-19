<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportItemsRequest;
use App\Services\Inventory\MenuImportService;
use Illuminate\Http\RedirectResponse;

class ItemImportController extends Controller
{
    /**
     * Import menu items and sizes from a CSV saved from Excel.
     */
    public function __invoke(ImportItemsRequest $request, MenuImportService $importer): RedirectResponse
    {
        $result = $importer->import($request->user()->business, $request->file('file')->getRealPath());

        $summary = "Imported: {$result['created']} new, {$result['updated']} updated.";

        return redirect()
            ->route('admin.inventory', ['tab' => 'menu'])
            ->with('status', $result['created'] + $result['updated'] > 0 ? $summary : null)
            ->with('import_errors', $result['errors']);
    }
}
