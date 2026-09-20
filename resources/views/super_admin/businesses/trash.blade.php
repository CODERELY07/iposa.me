<x-app-layout title="Trash">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        <a href="{{ route('super_admin.businesses.index') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> Businesses
        </a>

        <x-page-header eyebrow="Tenants" title="Trash"
            description="Shops you removed. Nobody there can sign in, and they are left out of every list and metric. Their data is still here until you erase it." />

        @if ($businesses->isEmpty())
            <section class="surface px-5 py-16 text-center">
                <p class="font-medium">The trash is empty.</p>
                <p class="mt-1 text-sm text-ink-500">Removed shops land here first, so a mistake is never final.</p>
            </section>
        @else
            <div class="space-y-4">
                @foreach ($businesses as $business)
                    @php($inside = $contents[$business->id])
                    <section class="surface p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $business->business_name }}</p>
                                <p class="text-sm text-ink-500">
                                    {{ $business->business_type }}
                                    @if ($business->owner) · {{ $business->owner->name }} ({{ $business->owner->email }}) @endif
                                </p>
                                <p class="mt-2 text-xs text-ink-500">
                                    Removed {{ $business->deleted_at?->diffForHumans() }}
                                    @if ($business->deletion_reason) · <span class="text-ink-600 dark:text-ink-300">{{ $business->deletion_reason }}</span> @endif
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('super_admin.businesses.restore', $business) }}"
                                    data-confirm-title="Restore {{ $business->business_name }}?"
                                    data-confirm="It goes back to {{ $business->status->label() }}, and everyone there can sign in again."
                                    data-confirm-action="Restore">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-ghost px-3 py-1.5 text-xs" data-loading-text="Restoring…">Restore</button>
                                </form>

                                <form method="POST" action="{{ route('super_admin.businesses.erase', $business) }}"
                                    data-confirm-title="Erase {{ $business->business_name }} for good?"
                                    data-confirm="{{ $inside['orders'] }} orders, {{ $inside['items'] }} items, {{ $inside['audits'] }} audits, {{ $inside['expenses'] }} expenses and {{ $inside['users'] }} accounts are deleted. This cannot be undone."
                                    data-confirm-phrase="{{ $business->business_name }}"
                                    data-confirm-action="Erase for good" data-confirm-danger>
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="confirmation" value="{{ $business->business_name }}">
                                    <button type="submit" class="btn-quiet px-3 py-1.5 text-xs text-loss-600 dark:text-loss-400" data-loading-text="Erasing…">Erase for good</button>
                                </form>
                            </div>
                        </div>

                        <dl class="num mt-4 grid grid-cols-2 gap-3 border-t border-ink-100 pt-4 text-sm sm:grid-cols-5 dark:border-white/[0.06]">
                            @foreach (['orders' => 'Orders', 'items' => 'Items', 'audits' => 'Audits', 'expenses' => 'Expenses', 'users' => 'Accounts'] as $key => $label)
                                <div>
                                    <dt class="text-xs font-normal uppercase tracking-wide text-ink-500">{{ $label }}</dt>
                                    <dd class="font-semibold">{{ number_format($inside[$key]) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>

            @if ($businesses->hasPages())
                <div class="flex items-center justify-between text-xs text-ink-500">
                    <span class="num">Showing {{ $businesses->firstItem() }}–{{ $businesses->lastItem() }} of {{ $businesses->total() }}</span>
                    <div class="flex gap-1">
                        @if (! $businesses->onFirstPage())<a href="{{ $businesses->previousPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Previous</a>@endif
                        @if ($businesses->hasMorePages())<a href="{{ $businesses->nextPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Next</a>@endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
