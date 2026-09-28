<x-app-layout title="Closing audit cost settings">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-8">
        <a href="{{ route('admin.settings') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> Settings
        </a>

        <x-page-header title="Closing audit cost settings" description="Every night the count finds extra usage beyond what recipes explain — waste, bigger portions, spills. Choose which items charge that extra against profit. The physical count and stock still update either way; this only changes what hits your numbers." />

        @if ($items->isEmpty())
            <section class="surface p-6 text-sm text-ink-500">
                Nothing is counted at closing yet. Add bulk items or liquids in <a href="{{ route('admin.inventory', ['tab' => 'bulk']) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Inventory</a> first.
            </section>
        @else
            <form method="POST" action="{{ route('admin.audit-cost-settings.update') }}"
                x-data="{ included: @js($items->where('include_audit_cost', true)->pluck('id')->values()) }">
                @csrf
                @method('PUT')

                <section class="surface p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-ink-500"><span class="num" x-text="included.length"></span> of {{ $items->count() }} counted</p>
                        <div class="flex gap-2">
                            <button type="button" @click="included = @js($items->pluck('id')->values())" class="btn-ghost text-xs">Check all</button>
                            <button type="button" @click="included = []" class="btn-ghost text-xs">Uncheck all</button>
                        </div>
                    </div>

                    <ul class="mt-4 divide-y divide-ink-100 dark:divide-white/[0.06]">
                        @foreach ($items as $item)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $item->name }}</p>
                                    <p class="text-xs text-ink-500">{{ $item->kind === \App\Enums\ItemKind::Piece ? 'Piece' : 'Bulk & liquid' }}{{ $item->unit ? ' · '.$item->unit : '' }}</p>
                                </div>
                                <label class="flex shrink-0 cursor-pointer items-center gap-2">
                                    <input type="checkbox" name="included[]" value="{{ $item->id }}" x-model.number="included"
                                        class="size-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm text-ink-600 dark:text-ink-300" x-text="included.includes({{ $item->id }}) ? 'Deducts' : 'Not deducted'"></span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="btn-primary" data-loading-text="Saving…">Save</button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
