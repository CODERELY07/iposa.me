{{-- Success message from the last action, and the first validation error of a normal form post. --}}
@php
    $status = session('status');
    $firstError = $errors->any() && ! $errors->hasBag('userDeletion') ? $errors->first() : null;
@endphp

@if ($status || $firstError)
    <div x-data="{ open: true }" x-show="open" x-init="setTimeout(() => open = false, {{ $firstError ? 9000 : 5000 }})" x-transition.opacity
        class="pointer-events-none fixed inset-x-0 top-3 z-[90] flex justify-center px-4 lg:left-64" role="status" aria-live="polite">
        <div @class([
            'pointer-events-auto flex max-w-lg items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-xl backdrop-blur',
            'border-gain-500/30 bg-white/95 text-ink-800 dark:bg-ink-900/95 dark:text-ink-100' => ! $firstError,
            'border-loss-500/40 bg-white/95 text-ink-800 dark:bg-ink-900/95 dark:text-ink-100' => $firstError,
        ])>
            @if ($firstError)
                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-loss-500 text-white"><x-icon name="alert" class="size-3" /></span>
                <span>{{ $firstError }}</span>
            @else
                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-gain-500 text-white"><x-icon name="check" class="size-3" /></span>
                <span>{{ $status }}</span>
            @endif
            <button type="button" @click="open = false" class="ml-1 text-ink-400 hover:text-ink-700 dark:hover:text-white" aria-label="Dismiss"><x-icon name="x" class="size-4" /></button>
        </div>
    </div>
@endif
