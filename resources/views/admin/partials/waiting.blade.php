{{-- Things only the owner can settle: cashier deliveries, cashier link requests, recipe suggestions from the count. --}}
<section id="waiting" class="surface scroll-mt-8 p-6">
    <p class="eyebrow">Waiting for you</p>

    @if ($deliveries->isNotEmpty())
        <div class="mt-4">
            <h2 class="font-semibold">Deliveries to check</h2>
            <p class="text-xs text-ink-500">Restocks your cashiers recorded. Compare each one with the supplier's receipt and enter what you paid.</p>
            <ul class="mt-3 space-y-3">
                @foreach ($deliveries as $delivery)
                    <li class="rounded-xl border border-ink-200 p-4 dark:border-white/[0.07]">
                        <p class="text-sm font-medium">{{ $delivery->item?->name ?? 'Deleted item' }} · recorded <span class="num">{{ $delivery->describeRecorded() }}</span></p>
                        <p class="text-xs text-ink-500">Received by {{ $delivery->received_by }} · {{ $delivery->created_at->format('M j, g:i A') }}</p>
                        <form method="POST" action="{{ route('admin.deliveries.check', $delivery) }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label class="field-label" for="receipt_quantity_{{ $delivery->id }}">Receipt says</label>
                                <div class="flex items-center gap-2">
                                    <input id="receipt_quantity_{{ $delivery->id }}" name="receipt_quantity" type="number" min="0" step="any" required value="{{ \App\Models\Item::trimNumber((float) $delivery->quantity, 3) }}" class="field num w-24 text-center">
                                    <span class="text-sm text-ink-500">{{ $delivery->entryUnit() }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="field-label" for="paid_{{ $delivery->id }}">You paid (total)</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                    <input id="paid_{{ $delivery->id }}" name="paid" type="number" min="0" step="0.01" class="field num w-32 pl-7" placeholder="0.00">
                                </div>
                            </div>
                            @if ($expensesEnabled)
                                <label class="flex items-center gap-2 pb-3 text-sm">
                                    <input type="hidden" name="log_expense" value="0">
                                    <input type="checkbox" name="log_expense" value="1" checked class="size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-white/[0.06]">
                                    Log in expenses
                                </label>
                            @endif
                            <button type="submit" class="btn-primary ml-auto py-2" data-loading-text="Checking…">Confirm</button>
                        </form>
                        <p class="mt-2 text-[11px] text-ink-400">If the receipt says more than was recorded, the difference never reached the shelf and is logged as missing stock. If it says less, the count is corrected.</p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($recipeRequests->isNotEmpty())
        <div class="mt-6">
            <h2 class="font-semibold">Link change requests</h2>
            <p class="text-xs text-ink-500">Nothing changes until you approve.</p>
            <ul class="mt-3 space-y-3">
                @foreach ($recipeRequests as $change)
                    <li class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-ink-200 p-4 dark:border-white/[0.07]">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ $change->item?->name ?? 'Deleted item' }}</p>
                            <ul class="mt-1 list-inside list-disc text-sm text-ink-700 dark:text-ink-200">
                                @foreach ($change->summary() as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-1 text-xs text-ink-500">Asked by {{ $change->requested_by }} · {{ $change->created_at->format('M j, g:i A') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.recipe-changes.approve', $change) }}">
                                @csrf
                                <button type="submit" class="btn-ghost px-3 py-1.5 text-xs" data-loading-text="Saving…">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.recipe-changes.reject', $change) }}">
                                @csrf
                                <button type="submit" class="btn-quiet px-3 py-1.5 text-xs" data-loading-text="…">Reject</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($recipeFixes->isNotEmpty())
        <div class="mt-6">
            <h2 class="font-semibold">Recipes that use too much</h2>
            <p class="text-xs text-ink-500">At closing, the count found more left than the recipes allow for. Until the recipes match, sales take off and cost too much.</p>
            <ul class="mt-3 space-y-3">
                @foreach ($recipeFixes as $fix)
                    @php($unit = $fix['item']->unit ?: 'pc')
                    <li class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-ink-200 p-4 dark:border-white/[0.07]">
                        <div class="min-w-0 max-w-xl">
                            <p class="text-sm font-medium">{{ $fix['item']->name }}</p>
                            <p class="text-sm text-ink-700 dark:text-ink-200">
                                Recipes took <span class="num">{{ \App\Models\Item::trimNumber($fix['deducted']) }} {{ $unit }}</span> since the count before,
                                but only <span class="num">{{ \App\Models\Item::trimNumber($fix['used']) }} {{ $unit }}</span> was used
                                (<span class="num">{{ number_format($fix['share'] * 100, 0) }}%</span>).
                            </p>
                            <p class="mt-1 text-xs text-ink-500">
                                Counted {{ $fix['line']->audit->date->format('M j') }}.
                                @if ($fix['share'] > 0)
                                    Lowering sets each of the {{ $fix['recipes'] }} {{ \Illuminate\Support\Str::plural('recipe', $fix['recipes']) }} that use it to {{ number_format($fix['share'] * 100, 0) }}% of today's amount. One count can be off, so you can also edit recipes one by one.
                                @else
                                    None of it was used, so check those recipes by hand.
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-2">
                            @if ($fix['share'] > 0)
                                <form method="POST" action="{{ route('admin.recipe-fixes.apply', $fix['line']) }}">
                                    @csrf
                                    <button type="submit" class="btn-ghost px-3 py-1.5 text-xs" data-loading-text="Saving…">Lower the recipes</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.recipe-fixes.dismiss', $fix['line']) }}">
                                @csrf
                                <button type="submit" class="btn-quiet px-3 py-1.5 text-xs" data-loading-text="…">Keep as is</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
