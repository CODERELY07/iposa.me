@php
    $status = session('status');
    $mailFailed = $status === 'verification-link-failed';
    $requested = $status === 'manual-verification-requested' || auth()->user()->verification_requested_at !== null;
    $support = config('iposa.support');
@endphp

<x-guest-layout>
    <div>
        <p class="eyebrow">One last step</p>
        <h1 class="mt-2 font-display text-4xl text-ink-900 dark:text-white">Verify your email.</h1>
        <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">
            We sent a link to <span class="font-medium text-ink-900 dark:text-white">{{ auth()->user()->email }}</span>. Click it to open your shop.
        </p>
    </div>

    @if ($status === 'verification-link-sent')
        <p class="mt-6 rounded-xl bg-gain-500/10 px-4 py-3 text-sm text-gain-700 dark:text-gain-300">A new verification link is on its way. Check your inbox and spam folder.</p>
    @endif

    @if ($mailFailed)
        <div class="mt-6 rounded-xl border border-brand-400/40 bg-brand-400/10 px-4 py-3 text-sm text-ink-700 dark:text-ink-200" role="alert">
            <p class="font-semibold">We couldn't send the email right now.</p>
            <p class="mt-1">Your account is saved. Ask an iPOSa agent to verify you below. It usually takes a few minutes during business hours.</p>
        </div>
    @endif

    @if ($requested)
        <div class="mt-6 rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-ink-700 dark:text-ink-200">
            <p class="font-semibold">Verification requested.</p>
            <p class="mt-1">An iPOSa agent will verify your account. Once they do, tap “I've been verified” below.</p>
        </div>
    @endif

    <div class="mt-8 space-y-3">
        <a href="{{ route('verification.notice') }}" class="btn-primary w-full py-3">I've been verified, continue</a>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn-ghost w-full py-3" data-loading-text="Sending…">Resend the email</button>
        </form>
    </div>

    <section class="mt-8 rounded-2xl border border-ink-200 p-5 dark:border-white/[0.08]">
        <h2 class="text-sm font-semibold">Didn't get the email?</h2>
        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Please contact our agent. They can verify your account for you.</p>

        @unless ($requested)
            <form method="POST" action="{{ route('verification.request-agent') }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-ghost w-full" data-loading-text="Sending request…">Ask an agent to verify me</button>
            </form>
        @endunless

        @if ($support['email'] || $support['phone'] || $support['messenger_url'])
            <ul class="mt-4 space-y-1.5 text-sm">
                @if ($support['email'])
                    <li>Email: <a href="mailto:{{ $support['email'] }}?subject={{ rawurlencode('Please verify my iPOSa account: '.auth()->user()->email) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">{{ $support['email'] }}</a></li>
                @endif
                @if ($support['phone'])
                    <li>Phone / Viber: <span class="num font-medium">{{ $support['phone'] }}</span></li>
                @endif
                @if ($support['messenger_url'])
                    <li><a href="{{ $support['messenger_url'] }}" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Message us on Messenger</a></li>
                @endif
            </ul>
        @endif
    </section>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-ink-500 hover:text-ink-900 hover:underline dark:hover:text-white">Log out</button>
    </form>
</x-guest-layout>
