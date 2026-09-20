<x-guest-layout>
    <div>
        <h1 class="font-display text-4xl text-ink-900 dark:text-white">Open the shop.</h1>
        <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">Log in as owner, manager or cashier. You'll land on the right screen.</p>
    </div>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@yourshop.ph" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="mb-1.5 text-xs font-medium text-brand-600 hover:underline dark:text-brand-300" href="{{ route('password.request') }}">
                        {{ __('Forgot it?') }}
                    </a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2.5">
            <input id="remember_me" type="checkbox" class="size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-ink-900 dark:focus:ring-offset-ink-950" name="remember">
            <span class="text-sm text-ink-600 dark:text-ink-400">{{ __('Keep me signed in on this device') }}</span>
        </label>

        <x-primary-button class="w-full py-3" data-loading-text="Logging you in…">
            {{ __('Log in') }}
            <x-icon name="arrow-right" class="size-4" />
        </x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500 dark:text-ink-400">
        New to iPOSa?
        <a href="{{ route('register') }}" class="font-semibold text-ink-900 hover:underline dark:text-white">Start a free 14-day trial</a>
    </p>
</x-guest-layout>
