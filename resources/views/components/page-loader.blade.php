{{-- Shown while the browser loads the next page. Driven by $store.loader in app.js. --}}
<div x-data x-show="$store.loader.visible" x-cloak class="pointer-events-none fixed inset-0 z-[100]" role="status" aria-live="polite">
    <div class="absolute inset-x-0 top-0 h-0.5 overflow-hidden bg-brand-400/20">
        <div class="h-full w-1/3 animate-[loader-slide_1.1s_ease-in-out_infinite] rounded-full bg-brand-400"></div>
    </div>

    <div x-show="$store.loader.slow" x-transition.opacity class="absolute inset-x-0 top-6 flex justify-center">
        <div class="flex items-center gap-2.5 rounded-full border border-ink-200 bg-white/95 px-4 py-2 text-sm font-medium text-ink-700 shadow-lg backdrop-blur dark:border-white/10 dark:bg-ink-900/95 dark:text-ink-200">
            <x-spinner class="size-4 text-brand-500" />
            Please wait a moment…
        </div>
    </div>
</div>
