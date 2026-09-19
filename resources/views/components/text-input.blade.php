@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'field py-2.5']) }}>
