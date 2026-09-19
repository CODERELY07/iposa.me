<?php

namespace App\Http\Controllers;

use App\Enums\ItemKind;
use App\Http\Requests\SubmitAuditRequest;
use App\Models\Item;
use App\Services\Audit\ClosingAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditController extends Controller
{
    /**
     * Tonight's shelf count: bulk items with what the system expects.
     */
    public function index(Request $request, ClosingAuditService $audits): View
    {
        $business = $request->user()->business;
        $todaysAudit = $audits->forDate($business, now());
        $countedToday = $todaysAudit?->lines->keyBy('item_id');

        $items = Item::query()
            ->active()
            ->ofKind(ItemKind::Bulk)
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit ?? 'unit',
                // A correction starts from the numbers already saved tonight.
                'expected' => (float) ($countedToday?->get($item->id)?->expected ?? $item->on_hand ?? 0),
                'counted' => $countedToday?->has($item->id) ? (float) $countedToday->get($item->id)->counted : null,
                'unitCost' => (float) ($item->unit_cost ?? 0),
            ])
            ->values()
            ->all();

        return view('audit.index', [
            'bulkItems' => $items,
            'todaysAudit' => $todaysAudit,
            'canCorrect' => Gate::allows('correct-audit'),
            'canSeeCosts' => Gate::allows('view-costs'),
        ]);
    }

    /**
     * Save the counts (JSON). A second submit the same day is a correction, owners only.
     */
    public function store(SubmitAuditRequest $request, ClosingAuditService $audits): JsonResponse
    {
        $business = $request->user()->business;

        if ($audits->forDate($business, now()) !== null) {
            Gate::authorize('correct-audit');
        }

        $audit = $audits->submit(
            $business,
            $request->user(),
            $request->counts(),
            $request->filled('started_at') ? Carbon::parse($request->validated('started_at')) : null,
        );

        return response()->json([
            'audit' => [
                'id' => $audit->id,
                'usage_cost' => Gate::allows('view-costs') ? $audit->usageCost() : null,
                'duration_seconds' => $audit->duration_seconds,
            ],
        ]);
    }
}
