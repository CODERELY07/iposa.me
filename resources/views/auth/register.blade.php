@php
    $businessTypes = \App\Http\Requests\Admin\UpdateBusinessProfileRequest::BUSINESS_TYPES;
@endphp

<x-guest-layout>
    <div>
        <p class="eyebrow">14 days free · no card needed</p>
        <h1 class="mt-2 font-display text-4xl text-ink-900 dark:text-white">Set up your shop.</h1>
        <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">Takes about two minutes. You can bring in your Excel menu on the next screen.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="name" :value="__('Your name')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Maria Santos" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="business_name" :value="__('Business name')" />
                <x-text-input id="business_name" type="text" name="business_name" :value="old('business_name')" required maxlength="255" placeholder="Kape't Burger" />
                <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
            </div>
        </div>

        <fieldset>
            <legend class="field-label">What do you sell?</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($businessTypes as $businessType)
                    <label class="cursor-pointer">
                        <input type="radio" name="business_type" value="{{ $businessType }}" class="peer sr-only" @checked(old('business_type', $businessTypes[0]) === $businessType)>
                        <span class="block rounded-xl border border-ink-200 px-3 py-2.5 text-sm text-ink-600 transition peer-checked:border-brand-400 peer-checked:bg-brand-400/10 peer-checked:text-ink-900 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400 dark:border-white/10 dark:text-ink-300 dark:peer-checked:text-white">
                            {{ $businessType }}
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <x-primary-button class="w-full py-3" data-loading-text="Setting up your shop…">
            {{ __('Create my shop') }}
            <x-icon name="arrow-right" class="size-4" />
        </x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500 dark:text-ink-400">
        {{ __('Already have a shop?') }}
        <a href="{{ route('login') }}" class="font-semibold text-ink-900 hover:underline dark:text-white">Log in</a>
    </p>
</x-guest-layout>
