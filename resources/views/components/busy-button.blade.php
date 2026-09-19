{{--
    Button that answers every click: idle → spinner + loading text → check + done text.
    The three labels share one grid cell so the button never changes width.
--}}
@props([
    'loadingText' => 'Please wait…',
    'doneText' => null,
    'duration' => 900,
    'type' => 'button',
])

<button type="{{ $type }}" x-data="busyAction({{ (int) $duration }}, {{ $doneText ? 'true' : 'false' }})" @click="run()"
    :disabled="state !== 'idle'" :aria-busy="busy.toString()" {{ $attributes->merge(['class' => 'disabled:!opacity-100']) }}>
    <span class="grid">
        <span :style="{ visibility: state === 'idle' ? 'visible' : 'hidden' }" class="col-start-1 row-start-1 inline-flex items-center justify-center gap-2">{{ $slot }}</span>
        <span style="visibility: hidden" :style="{ visibility: state === 'busy' ? 'visible' : 'hidden' }" class="col-start-1 row-start-1 inline-flex items-center justify-center gap-2">
            <x-spinner /> {{ $loadingText }}
        </span>
        @if ($doneText)
            <span style="visibility: hidden" :style="{ visibility: state === 'done' ? 'visible' : 'hidden' }" class="col-start-1 row-start-1 inline-flex items-center justify-center gap-2">
                <x-icon name="check" class="size-4" /> {{ $doneText }}
            </span>
        @endif
    </span>
</button>
