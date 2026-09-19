<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetRequest;
use App\Models\Asset;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    /**
     * Add equipment. A cash purchase (1 term) is logged as paid right away.
     */
    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $terms = (int) $data['terms'];

        DB::transaction(function () use ($data, $terms, $request): void {
            $asset = Asset::create([
                'name' => $data['name'],
                'vendor' => $data['vendor'] ?? null,
                'price' => $data['price'],
                'installment_amount' => $terms === 1 ? null : $data['installment_amount'],
                'terms' => $terms,
                'paid_count' => (int) ($data['paid_count'] ?? 0),
                'first_due_on' => $data['first_due_on'],
            ]);

            if ($terms === 1 && $asset->paid_count === 0) {
                $this->recordPayment($asset, $request);
            }
        });

        return redirect()->route('admin.expenses', ['tab' => 'assets'])->with('status', 'Equipment added.');
    }

    /**
     * Mark the next installment as paid: logs an expense so it lands in the P&L exactly once.
     */
    public function pay(Request $request, Asset $asset): RedirectResponse
    {
        if ($asset->isFullyPaid()) {
            throw ValidationException::withMessages(['asset' => "{$asset->name} is already fully paid."]);
        }

        DB::transaction(fn () => $this->recordPayment($asset, $request));

        return redirect()->route('admin.expenses', ['tab' => 'assets'])->with('status', "Payment for {$asset->name} recorded.");
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return redirect()->route('admin.expenses', ['tab' => 'assets'])->with('status', 'Equipment removed. Its past payments stay in your expenses.');
    }

    private function recordPayment(Asset $asset, Request $request): void
    {
        $installment = $asset->paid_count + 1;

        Expense::create([
            'date' => today(),
            'category' => ExpenseCategory::Payables,
            'description' => $asset->terms === 1 ? "{$asset->name} (cash purchase)" : "{$asset->name} · installment {$installment} of {$asset->terms}",
            'kind' => ExpenseKind::Fixed,
            'amount' => $asset->paymentAmount(),
            'asset_id' => $asset->id,
            'user_id' => $request->user()->id,
            'logged_by' => $request->user()->name,
        ]);

        $asset->increment('paid_count');
    }
}
