@php
    // Component attributes arrive HTML-escaped; decode once so the <title> isn't escaped twice.
    $title = htmlspecialchars_decode((string) $attributes->get('title'), ENT_QUOTES);
    $isFocusMode = (bool) $attributes->get('focus', false);
    $currentUser = auth()->user();
    $role = $currentUser?->role;
    $shop = $currentUser?->business;
    $canRunAudit = $currentUser?->can('run-audit') ?? false;

    $navigation = match ($role) {
        'super_admin' => [
            ['label' => 'Overview', 'route' => 'super_admin.dashboard', 'icon' => 'home'],
            ['label' => 'Businesses', 'route' => 'super_admin.businesses.index', 'icon' => 'building', 'match' => 'super_admin.businesses.*'],
            ['label' => 'Plans & billing', 'route' => 'super_admin.plans', 'icon' => 'card'],
        ],
        'admin' => array_values(array_filter([
            ['label' => 'Today', 'route' => 'admin.dashboard', 'icon' => 'home'],
            ['label' => 'Register', 'route' => 'pos', 'icon' => 'pos'],
            ['label' => 'Inventory', 'route' => 'admin.inventory', 'icon' => 'box', 'match' => 'admin.inventory*'],
            ['label' => 'Closing audit', 'route' => 'audit', 'icon' => 'audit'],
            $shop?->hasFeature('expenses') ? ['label' => 'Expenses', 'route' => 'admin.expenses', 'icon' => 'receipt'] : null,
            $shop?->hasFeature('reports') ? ['label' => 'Profit & ledger', 'route' => 'admin.reports', 'icon' => 'chart'] : null,
            ['label' => 'Team', 'route' => 'admin.team', 'icon' => 'users'],
            ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'cog'],
        ])),
        default => array_values(array_filter([
            ['label' => 'Register', 'route' => 'pos', 'icon' => 'pos'],
            $canRunAudit ? ['label' => 'Closing audit', 'route' => 'audit', 'icon' => 'audit'] : null,
            ['label' => 'My orders', 'route' => 'staff.orders', 'icon' => 'receipt'],
        ])),
    };

    $workspaceName = $role === 'super_admin' ? 'Platform console' : ($shop?->business_name ?? 'No shop linked');
    $businessCount = $role === 'super_admin' ? \App\Models\Business::count() : 0;
    $workspaceMeta = match ($role) {
        'super_admin' => 'Operator · '.number_format($businessCount).' '.\Illuminate\Support\Str::plural('business', $businessCount),
        'admin' => 'Owner · '.($shop?->planDetails()['name'] ?? '').' plan',
        default => 'Cashier',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans antialiased">
        <x-page-loader />
        <div x-data="{ drawerOpen: false }" class="min-h-dvh lg:flex">
            {{-- Mobile top bar --}}
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-ink-200 bg-ink-50/90 px-4 backdrop-blur lg:hidden dark:border-white/[0.06] dark:bg-ink-950/90">
                <button type="button" class="btn-quiet size-9 !px-0" @click="drawerOpen = true" aria-label="Open navigation">
                    <x-icon name="menu" />
                </button>
                <x-brand-mark class="text-lg" />
                <x-theme-toggle />
            </header>

            {{-- Drawer backdrop (mobile) --}}
            <div x-show="drawerOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-ink-950/60 lg:hidden" @click="drawerOpen = false"></div>

            {{-- Sidebar --}}
            <aside
                :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-ink-200 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0 dark:border-white/[0.06] dark:bg-ink-950 {{ $isFocusMode ? 'lg:w-[76px]' : 'lg:w-64' }}"
            >
                <div class="flex h-16 items-center justify-between px-5 {{ $isFocusMode ? 'lg:justify-center lg:px-0' : '' }}">
                    <a href="{{ route('dashboard') }}" class="text-xl">
                        @if ($isFocusMode)
                            <span class="hidden size-9 items-center justify-center rounded-xl bg-brand-400 font-bold text-ink-950 lg:inline-flex">i.</span>
                            <x-brand-mark class="lg:hidden" />
                        @else
                            <x-brand-mark />
                        @endif
                    </a>
                    <button type="button" class="btn-quiet size-9 !px-0 lg:hidden" @click="drawerOpen = false" aria-label="Close navigation">
                        <x-icon name="x" />
                    </button>
                </div>

                <div class="mx-3 mb-3 rounded-xl border border-ink-200 px-3 py-2.5 dark:border-white/[0.07] {{ $isFocusMode ? 'lg:hidden' : '' }}">
                    <p class="truncate text-sm font-semibold">{{ $workspaceName }}</p>
                    <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $workspaceMeta }}</p>
                </div>

                <nav class="flex-1 space-y-0.5 overflow-y-auto px-3">
                    @foreach ($navigation as $item)
                        @php($isActive = request()->routeIs($item['match'] ?? $item['route']))
                        <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                            @class([
                                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                'lg:justify-center lg:px-0' => $isFocusMode,
                                'bg-ink-100 text-ink-900 dark:bg-white/[0.07] dark:text-white' => $isActive,
                                'text-ink-500 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-400 dark:hover:bg-white/[0.04] dark:hover:text-ink-100' => ! $isActive,
                            ])>
                            <x-icon :name="$item['icon']" :class="$isActive ? 'size-5 text-brand-500 dark:text-brand-400' : 'size-5'" />
                            <span @class(['lg:hidden' => $isFocusMode])>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="space-y-2 border-t border-ink-200 p-3 dark:border-white/[0.06]">
                    <div x-data="{ online: navigator.onLine }" @online.window="online = true" @offline.window="online = false"
                        @class(['flex items-center gap-2 px-2 text-xs text-ink-500 dark:text-ink-400', 'lg:justify-center' => $isFocusMode])>
                        <span class="relative flex size-2">
                            <span x-show="online" class="absolute inline-flex size-full animate-ping rounded-full bg-gain-400 opacity-60"></span>
                            <span :class="online ? 'bg-gain-500' : 'bg-loss-500'" class="relative inline-flex size-2 rounded-full bg-gain-500"></span>
                        </span>
                        <span @class(['lg:hidden' => $isFocusMode]) x-text="online ? 'Online' : 'Offline · sales need internet'">Online</span>
                    </div>

                    <div @class(['flex items-center justify-between gap-2', 'lg:flex-col' => $isFocusMode])>
                        <a href="{{ route('profile.edit') }}" @class(['flex min-w-0 items-center gap-2.5 rounded-xl p-1.5 hover:bg-ink-100 dark:hover:bg-white/[0.04]'])>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink-200 text-xs font-semibold uppercase dark:bg-white/10">
                                {{ \Illuminate\Support\Str::of(auth()->user()?->name ?? 'U')->substr(0, 2) }}
                            </span>
                            <span @class(['min-w-0', 'lg:hidden' => $isFocusMode])>
                                <span class="block truncate text-sm font-medium">{{ auth()->user()?->name }}</span>
                                <span class="block truncate text-xs capitalize text-ink-500">{{ str_replace('_', ' ', $role ?? '') }}</span>
                            </span>
                        </a>
                        <div @class(['flex items-center', 'lg:flex-col' => $isFocusMode])>
                            <x-theme-toggle class="hidden lg:inline-flex" />
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn-quiet size-9 !px-0" aria-label="Log out" title="Log out" data-loading-text="">
                                    <x-icon name="logout" class="size-[18px]" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <x-flash />

                @isset($header)
                    <div class="border-b border-ink-200 px-4 py-6 sm:px-8 dark:border-white/[0.06]">
                        {{ $header }}
                    </div>
                @endisset

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
