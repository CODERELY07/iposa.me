@php
    $alreadyClosed = $todaysAudit !== null;
    $usedLabel = $countsPieces ? 'Used or missing today' : 'Bulk used today';
    $auditConfig = [
        'items' => $bulkItems,
        'storeUrl' => route('audit.store', absolute: false),
        'alreadyClosed' => $alreadyClosed,
    ];
@endphp

<x-app-layout title="Closing audit">
    <div x-data="closingAudit(@js($auditConfig))" class="mx-auto max-w-2xl px-4 pb-40 pt-6 sm:px-6">
        @if (empty($bulkItems))
            <div class="py-20 text-center">
                <h1 class="text-2xl font-semibold">Nothing to count yet</h1>
                <p class="mx-auto mt-2 max-w-sm text-sm text-ink-500">
                    The closing audit lists your bulk & liquid items: oil, mayo, sauces, LPG{{ $countsPieces ? ', and your pieces: buns, patties, cups' : '' }}.
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.inventory.create', ['kind' => 'bulk']) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Add the first one</a>.
                    @else
                        Ask the owner to add them in Inventory.
                    @endif
                </p>
            </div>
        @else
            {{-- Already closed today --}}
            <template x-if="! editing && ! submitted">
                <div class="flex min-h-[60dvh] flex-col items-center justify-center text-center">
                    <div class="flex size-16 items-center justify-center rounded-full bg-gain-500/15 text-gain-600 dark:text-gain-300">
                        <x-icon name="check" class="size-8" />
                    </div>
                    <h1 class="mt-5 text-2xl font-semibold">Today is already closed</h1>
                    <p class="mt-2 max-w-xs text-sm text-ink-500 dark:text-ink-400">
                        Counted by {{ $todaysAudit?->counted_by }} at {{ $todaysAudit?->submitted_at?->format('g:i A') }}.
                    </p>
                    @if ($canCorrect)
                        <button type="button" @click="editing = true" class="btn-ghost mt-8">Correct tonight's counts</button>
                    @else
                        <a href="{{ route('dashboard') }}" class="btn-ghost mt-8">Back to the register</a>
                    @endif
                </div>
            </template>

            <template x-if="editing && ! submitted">
                <div>
                    <div>
                        <p class="eyebrow">Closing audit · {{ now()->format('D j M') }}</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $alreadyClosed ? 'Correct tonight’s counts' : 'What’s left on the shelf?' }}</h1>
                        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Tap the full containers and how full the open one is. Or type what you see: half a bottle is 0.5.</p>
                    </div>

                    {{-- Progress --}}
                    <div class="mt-6 flex items-center gap-3">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink-200 dark:bg-white/10">
                            <div class="h-full rounded-full bg-brand-400 transition-all" :style="`width: ${touchedCount / items.length * 100}%`"></div>
                        </div>
                        <span class="num text-xs text-ink-500"><span x-text="touchedCount"></span>/<span x-text="items.length"></span></span>
                    </div>

                    <ul class="mt-6 space-y-3">
                        <template x-for="item in items" :key="item.id">
                            <li :class="item.touched ? 'border-ink-200 dark:border-white/[0.07]' : 'border-brand-400/40'"
                                class="rounded-2xl border bg-white p-4 transition dark:bg-ink-900/60">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold" x-text="item.name"></p>
                                        <p class="text-xs text-ink-500">
                                            <template x-if="! item.rows.length"><span>per <span x-text="item.unit"></span> · </span></template>
                                            system says <span class="num font-medium text-ink-700 dark:text-ink-300" x-text="item.rows.length ? amount(item, item.expected) : item.expected"></span>
                                        </p>
                                    </div>
                                    <template x-if="item.touched && item.counted < item.expected">
                                        <span class="pill shrink-0 bg-ink-100 text-ink-600 dark:bg-white/[0.06] dark:text-ink-300">
                                            used <span class="num" x-text="item.rows.length ? amount(item, item.expected - item.counted) : (item.expected - item.counted).toFixed(2).replace(/\.?0+$/, '')"></span>
                                        </span>
                                    </template>
                                    <template x-if="item.touched && item.counted > item.expected && ! item.inRecipes">
                                        <span class="pill shrink-0 bg-brand-400/15 text-brand-700 dark:text-brand-300">restocked?</span>
                                    </template>
                                </div>

                                {{-- Bought in containers: tap full ones and how full the open one is --}}
                                <template x-if="item.rows.length">
                                    <div class="mt-4 space-y-3">
                                        <template x-for="row in item.rows" :key="row.label">
                                            <div class="space-y-2 rounded-xl bg-ink-100/60 p-3 dark:bg-white/[0.04]">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-sm font-medium">Full <span x-text="row.label + 's'"></span> <span class="text-xs font-normal text-ink-500" x-text="'(' + amount(item, row.size) + ' each)'"></span></p>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" @click="bumpFull(item, row, -1)" class="btn-ghost size-11 !rounded-xl !px-0" :aria-label="'One fewer full ' + row.label"><x-icon name="minus" /></button>
                                                        <span class="num w-8 text-center text-xl font-semibold" x-text="row.full"></span>
                                                        <button type="button" @click="bumpFull(item, row, 1)" class="btn-ghost size-11 !rounded-xl !px-0" :aria-label="'One more full ' + row.label"><x-icon name="plus" /></button>
                                                    </div>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-xs text-ink-500">Open <span x-text="row.label"></span></p>
                                                    <div class="grid grid-cols-5 gap-1.5">
                                                        <template x-for="choice in [{ label: 'Empty', value: 0 }, { label: '¼', value: 0.25 }, { label: '½', value: 0.5 }, { label: '¾', value: 0.75 }, { label: 'Full', value: 1 }]" :key="choice.label">
                                                            <button type="button" @click="setOpen(item, row, choice.value)"
                                                                :class="row.open === choice.value && item.touched && item.exact === '' ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-950' : 'bg-white text-ink-700 ring-1 ring-ink-200 dark:bg-ink-900 dark:text-ink-200 dark:ring-white/10'"
                                                                class="h-11 rounded-xl text-sm font-semibold transition" x-text="choice.label"></button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-sm">Counted <span class="num font-semibold" x-text="amount(item, item.counted)"></span></p>
                                            <label class="flex items-center gap-2 text-xs text-ink-500">
                                                or exactly
                                                <input type="number" inputmode="decimal" min="0" step="any" :value="item.exact" @input="setExact(item, $event.target.value)"
                                                    class="field num h-9 w-28 py-1 text-right" :placeholder="item.unit" :aria-label="item.name + ' exact amount'">
                                            </label>
                                        </div>
                                    </div>
                                </template>

                                {{-- Counted in its own unit: − / + by a step that suits the unit --}}
                                <template x-if="! item.rows.length">
                                    <div class="mt-4 flex items-center gap-2">
                                        <button type="button" @click="step(item, -item.step)" class="btn-ghost size-14 shrink-0 !rounded-2xl !px-0" :aria-label="'Minus ' + item.step">
                                            <x-icon name="minus" />
                                        </button>
                                        <input type="number" inputmode="decimal" :step="item.step" min="0"
                                            :value="item.counted" @input="set(item, $event.target.value)" @focus="$event.target.select()"
                                            class="field num h-14 flex-1 text-center text-2xl font-semibold" :aria-label="item.name + ' count'">
                                        <button type="button" @click="step(item, item.step)" class="btn-ghost size-14 shrink-0 !rounded-2xl !px-0" :aria-label="'Plus ' + item.step">
                                            <x-icon name="plus" />
                                        </button>
                                    </div>
                                </template>

                                {{-- A liquid in recipes counted higher than expected: restock, or recipes set too high? --}}
                                <template x-if="item.inRecipes && item.touched && item.counted > item.expected">
                                    <fieldset class="mt-3 rounded-xl border border-brand-400/40 bg-brand-400/[0.06] p-3">
                                        <legend class="px-1 text-xs font-semibold text-brand-800 dark:text-brand-200">
                                            <span x-text="(item.rows.length ? amount(item, item.counted - item.expected) : (item.counted - item.expected)) + ' more than the system expected'"></span>
                                        </legend>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" value="restock" x-model="item.surplus" class="text-brand-500 focus:ring-brand-400"> We restocked today
                                        </label>
                                        <label class="mt-1 flex items-center gap-2 text-sm">
                                            <input type="radio" value="recipe" x-model="item.surplus" class="text-brand-500 focus:ring-brand-400"> Recipes use less than set
                                        </label>
                                    </fieldset>
                                </template>

                                <button type="button" x-show="! item.touched" @click="confirmUnchanged(item)"
                                    class="mt-2 w-full rounded-xl py-2 text-xs font-medium text-ink-500 hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-white/5 dark:hover:text-white">
                                    Still <span class="num" x-text="item.rows.length ? amount(item, item.expected) : item.expected"></span>, nothing used
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            <template x-if="submitted">
                <div class="flex min-h-[60dvh] flex-col items-center justify-center text-center">
                    <div class="flex size-16 items-center justify-center rounded-full bg-gain-500/15 text-gain-600 dark:text-gain-300">
                        <x-icon name="check" class="size-8" />
                    </div>
                    <h1 class="mt-5 text-2xl font-semibold">Day closed. Salamat!</h1>
                    <p class="mt-2 max-w-xs text-sm text-ink-500 dark:text-ink-400">Your counts were saved. Today's profit report is now complete.</p>
                    @if ($canSeeCosts)
                        <p x-show="usageCost !== null" class="mt-3 text-sm">{{ $usedLabel }}: <span class="num font-semibold" x-text="formatPeso(usageCost)"></span></p>
                    @endif
                    <a href="{{ route('dashboard') }}" class="btn-ghost mt-8">Done</a>
                </div>
            </template>

            {{-- Sticky submit --}}
            <div x-show="editing && ! submitted" x-cloak class="fixed inset-x-0 bottom-0 z-20 border-t border-ink-200 bg-ink-50/95 px-4 pt-3 backdrop-blur lg:left-64 dark:border-white/[0.06] dark:bg-ink-950/95"
                style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
                <div class="mx-auto max-w-2xl">
                    <p x-show="error" x-cloak role="alert" class="mb-3 flex gap-2 rounded-xl bg-loss-500/10 px-3 py-2 text-sm text-loss-700 dark:text-loss-300">
                        <x-icon name="alert" class="mt-0.5 size-4" /> <span x-text="error"></span>
                    </p>
                    <div class="flex items-center gap-4">
                        @if ($canSeeCosts)
                            <div class="min-w-0">
                                <p class="text-xs text-ink-500">{{ $usedLabel }}</p>
                                <p class="num text-lg font-semibold" x-text="formatPeso(usageValue)"></p>
                            </div>
                        @else
                            <p class="min-w-0 text-xs text-ink-500"><span class="num" x-text="items.length - touchedCount"></span> items left to check</p>
                        @endif
                        <button type="button" @click="submit()" :disabled="touchedCount < items.length || saving" :aria-busy="saving.toString()"
                            :class="saving ? '!opacity-100' : ''" class="btn-primary ml-auto rounded-2xl px-6 py-3.5">
                            <template x-if="! saving"><span x-text="error ? 'Try again' : @js($alreadyClosed ? 'Save corrections' : 'Close the day')"></span></template>
                            <template x-if="saving">
                                <span class="inline-flex items-center gap-2"><x-spinner /> Saving counts…</span>
                            </template>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
