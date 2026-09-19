<x-app-layout title="Register paused">
    <div class="mx-auto flex min-h-[70dvh] max-w-md flex-col items-center justify-center px-4 text-center">
        <div class="flex size-16 items-center justify-center rounded-full bg-brand-400/15 text-brand-600 dark:text-brand-300">
            <x-icon name="clock" class="size-8" />
        </div>
        <h1 class="mt-5 text-2xl font-semibold">The register is paused</h1>
        <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">
            {{ $business->business_name }}'s iPOSa subscription needs payment. Please ask the owner to open Settings → Plan & billing.
            Nothing was lost: all sales and counts are saved.
        </p>
        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="btn-ghost" data-loading-text="Logging out…">Log out</button>
        </form>
    </div>
</x-app-layout>
