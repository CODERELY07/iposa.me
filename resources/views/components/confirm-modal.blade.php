{{--
    One dialog for every destructive action. Never window.confirm/prompt:
    a form with data-confirm="…" opens this instead (see resources/js/app.js),
    and code can call $store.confirm.ask({…}) and await the answer.
--}}
<div x-data x-show="$store.confirm.open" x-cloak
    x-effect="if ($store.confirm.open) $nextTick(() => ($refs.field ?? $refs.accept)?.focus())"
    @keydown.escape.window="$store.confirm.open && $store.confirm.cancel()"
    class="fixed inset-0 z-[95] flex items-end justify-center bg-ink-950/70 p-0 backdrop-blur-sm sm:items-center sm:p-6"
    role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
    <div x-show="$store.confirm.open" x-transition.opacity.duration.150ms @click="$store.confirm.cancel()" class="absolute inset-0"></div>

    <div x-show="$store.confirm.open"
        x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-0 sm:scale-95"
        class="relative w-full max-w-md rounded-t-3xl border border-ink-200 bg-white p-6 shadow-2xl sm:rounded-3xl dark:border-white/10 dark:bg-ink-900">
        <h2 id="confirm-modal-title" class="text-lg font-semibold" x-text="$store.confirm.title"></h2>
        <p class="mt-2 text-sm text-ink-600 dark:text-ink-300" x-text="$store.confirm.message"></p>

        <template x-if="$store.confirm.promptLabel">
            <div class="mt-4">
                <label class="field-label" for="confirm-modal-note" x-text="$store.confirm.promptLabel"></label>
                <textarea id="confirm-modal-note" x-ref="field" x-model="$store.confirm.note" rows="3" maxlength="255"
                    class="field" :placeholder="$store.confirm.promptPlaceholder"></textarea>
            </div>
        </template>

        <template x-if="$store.confirm.phrase">
            <div class="mt-4">
                <label class="field-label" for="confirm-modal-phrase">
                    Type <span class="font-semibold text-ink-900 dark:text-white" x-text="$store.confirm.phrase"></span> to confirm
                </label>
                <input id="confirm-modal-phrase" x-ref="field" x-model="$store.confirm.typed" type="text" autocomplete="off" class="field">
            </div>
        </template>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" @click="$store.confirm.cancel()" class="btn-quiet" x-text="$store.confirm.cancelAction"></button>
            <button type="button" x-ref="accept" @click="$store.confirm.accept()" :disabled="! $store.confirm.ready"
                :class="$store.confirm.danger ? 'btn-primary !bg-loss-600 hover:!bg-loss-500 !text-white' : 'btn-primary'"
                class="disabled:cursor-not-allowed disabled:opacity-50" x-text="$store.confirm.action"></button>
        </div>
    </div>
</div>
