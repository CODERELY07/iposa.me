<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ItemKind;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\Audit\ClosingAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Which counted items charge profit for their extra usage at closing (waste,
 * bigger portions), and which don't. The physical count still happens either way.
 */
class AuditCostSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $business = $request->user()->business;

        $items = Item::query()
            ->active()
            ->whereIn('kind', ClosingAuditService::countedKinds($business))
            ->orderByRaw('case when kind = ? then 0 else 1 end', [ItemKind::Bulk->value])
            ->orderBy('name')
            ->get(['id', 'kind', 'name', 'unit', 'include_audit_cost']);

        return view('admin.audit-cost-settings', [
            'items' => $items,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $request->user()->business;

        $countedIds = Item::query()
            ->active()
            ->whereIn('kind', ClosingAuditService::countedKinds($business))
            ->pluck('id');

        $validated = $request->validate([
            'included' => ['nullable', 'array'],
            'included.*' => ['integer'],
        ]);

        $includedIds = collect($validated['included'] ?? [])->map(fn ($id) => (int) $id)->intersect($countedIds);

        Item::query()->whereIn('id', $countedIds)->update(['include_audit_cost' => false]);
        Item::query()->whereIn('id', $includedIds)->update(['include_audit_cost' => true]);

        return redirect()->route('admin.audit-cost-settings')->with('status', 'Closing audit cost settings saved.');
    }
}
