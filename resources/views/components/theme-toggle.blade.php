<button type="button" x-data @click="$store.theme.toggle()" {{ $attributes->merge(['class' => 'btn-quiet size-9 !px-0']) }}
    :aria-label="$store.theme.dark ? 'Switch to light mode' : 'Switch to dark mode'">
    <x-icon name="sun" class="size-[18px]" x-show="$store.theme.dark" />
    <x-icon name="moon" class="size-[18px]" x-show="! $store.theme.dark" x-cloak />
</button>
