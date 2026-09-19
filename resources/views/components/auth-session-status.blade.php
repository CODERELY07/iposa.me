@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-gain-600 dark:text-gain-400']) }}>
        {{ $status }}
    </div>
@endif
