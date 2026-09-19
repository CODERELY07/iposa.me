<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans antialiased">
        <div class="grid min-h-dvh lg:grid-cols-[1fr_minmax(0,560px)]">
            <div class="flex flex-col px-5 py-6 sm:px-10">
                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="text-xl"><x-brand-mark /></a>
                    <x-theme-toggle />
                </div>

                <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-12">
                    {{ $slot }}
                </div>

                <p class="text-center text-xs text-ink-500 lg:text-left">Your data is yours. Export to Excel any time, on every plan.</p>
            </div>

            <aside class="relative hidden overflow-hidden border-l border-white/[0.06] bg-ink-900 lg:flex lg:flex-col lg:items-center lg:justify-center lg:gap-10 lg:p-12">
                <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-brand-500/20 blur-3xl"></div>
                <x-closing-receipt class="rotate-[-2deg]" />
                <p class="max-w-xs text-center font-display text-2xl leading-snug text-ink-100">
                    Every night, the real number. <span class="italic text-brand-300">Not a guess.</span>
                </p>
            </aside>
        </div>
    </body>
</html>
