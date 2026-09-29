<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCashFloatRequest;
use App\Models\CashFloat;
use Illuminate\Http\RedirectResponse;

class CashFloatController extends Controller
{
    /**
     * Set today's starting cash and, once it's been counted, what the drawer actually
     * held. Only ever touches today: a closed day's drawer isn't reopened from here.
     */
    public function store(SaveCashFloatRequest $request): RedirectResponse
    {
        $user = $request->user();
        $countedAmount = $request->validated('counted_amount');

        $float = CashFloat::withoutGlobalScopes()
            ->where('business_id', $user->business_id)
            ->whereDate('date', today())
            ->first() ?? new CashFloat(['business_id' => $user->business_id, 'date' => today()]);

        $float->fill([
            'starting_amount' => $request->validated('starting_amount'),
            'started_by' => $float->started_by ?? $user->id,
            'started_by_name' => $float->started_by_name ?? $user->name,
        ]);

        if ($countedAmount !== null && $countedAmount !== '') {
            $float->fill([
                'counted_amount' => $countedAmount,
                'counted_by' => $user->id,
                'counted_by_name' => $user->name,
                'counted_at' => now(),
            ]);
        }

        $float->save();

        return redirect()->route('admin.day', ['section' => 'sales'])->with('status', 'Cash drawer saved.');
    }
}
