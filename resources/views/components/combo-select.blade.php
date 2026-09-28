{{--
    A searchable dropdown: type to filter a long list instead of scrolling a native <select>.
    `model`, `query` and `open` are raw Alpine expressions (property paths in the surrounding
    scope, e.g. "line.piece_item_id"), not Blade/PHP values — this component reads and writes
    them directly, so it works inside an x-for loop without a nested, colliding scope.

    Pass either `:options` (a plain PHP array of ['value' => ..., 'label' => ...], fixed at
    render time) or `options-expr` (a raw JS expression, e.g. "categories.map(c => ({value:
    c.id, label: c.name}))", re-evaluated live against the surrounding scope — use this when
    the list itself is reactive, like categories the owner can add on the fly).

    For a plain form submission, `name` is a static field name. Inside an x-for loop where the
    name has to include the row index, use `dynamic-name` with a raw JS expression instead,
    e.g. dynamic-name="`recipe[${index}][piece_item_id]`".
--}}
@props([
    'options' => [],
    'optionsExpr' => null,
    'model',
    'query',
    'open',
    'placeholder' => 'Search…',
    'name' => null,
    'dynamicName' => null,
    'id' => null,
    'ariaLabel' => null,
    'disabled' => 'false',
    'onSelect' => '',
])

<div {{ $attributes->merge(['class' => 'relative']) }}
    x-data="{ get options() { return {{ $optionsExpr ?? \Illuminate\Support\Js::from($options) }}; } }"
    @click.outside="{{ $open }} = false" @keydown.escape="{{ $open }} = false">
    @if ($dynamicName)
        <input type="hidden" :name="{{ $dynamicName }}" :value="{{ $model }}">
    @elseif ($name)
        <input type="hidden" name="{{ $name }}" :value="{{ $model }}">
    @endif
    <button type="button" @if ($id) id="{{ $id }}" @endif @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
        aria-haspopup="listbox" :aria-expanded="Boolean({{ $open }}).toString()"
        @click="if (! ({{ $disabled }})) { {{ $open }} = ! {{ $open }}; {{ $query }} = ''; }" :disabled="{{ $disabled }}"
        class="field flex w-full items-center justify-between gap-2 px-3 py-2 text-left disabled:cursor-not-allowed disabled:opacity-60">
        <span class="truncate" x-text="(options.find(o => String(o.value) === String({{ $model }})) || {}).label || '{{ $placeholder }}'"></span>
        <x-icon name="chevron-down" class="size-4 shrink-0 text-ink-400" />
    </button>
    <div x-show="{{ $open }}" x-cloak class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-ink-200 bg-white shadow-lg dark:border-white/10 dark:bg-ink-900">
        <div class="border-b border-ink-100 p-2 dark:border-white/[0.06]">
            <input type="search" x-model="{{ $query }}" @click.stop
                x-effect="if ({{ $open }}) $nextTick(() => $el.focus())"
                @keydown.enter.prevent="const m = options.filter(o => o.label.toLowerCase().includes(({{ $query }} || '').toLowerCase()))[0]; if (m) { {{ $model }} = m.value; {{ $open }} = false; {{ $onSelect }} }"
                placeholder="{{ $placeholder }}" class="field h-9 text-sm">
        </div>
        <ul class="max-h-56 overflow-y-auto py-1 text-sm">
            <template x-for="opt in options.filter(o => o.label.toLowerCase().includes(({{ $query }} || '').toLowerCase()))" :key="opt.value">
                <li @click="{{ $model }} = opt.value; {{ $open }} = false; {{ $onSelect }}"
                    :class="String(opt.value) === String({{ $model }}) ? 'bg-brand-400/10 font-medium' : ''"
                    class="cursor-pointer px-3 py-2 text-ink-900 hover:bg-ink-50 dark:text-ink-100 dark:hover:bg-white/5" x-text="opt.label"></li>
            </template>
            <li x-show="options.filter(o => o.label.toLowerCase().includes(({{ $query }} || '').toLowerCase())).length === 0" class="px-3 py-2 text-ink-400">No matches</li>
        </ul>
    </div>
</div>
