@php
    $faqs = [
        ['q' => 'What if the internet drops mid-rush?', 'a' => 'The register keeps taking orders offline and syncs when the connection is back. Nothing is lost.'],
        ['q' => 'Do I need a special POS machine?', 'a' => 'No. It runs in the browser on the tablet or phone you already have. Add it to your home screen and it behaves like an app.'],
        ['q' => 'Can my cashiers see my margins?', 'a' => 'Not unless you allow it. Cashiers see the register, the closing audit and their own orders. Costs stay with you.'],
        ['q' => 'I already track everything in Excel.', 'a' => 'Good. Import your menu sheet with sizes, cost and selling price. The ledger uses the same columns, and you can export back to Excel any time.'],
        ['q' => 'Do I have to set up recipes?', 'a' => 'Only if you want to. Link a burger to its bun, patty and cheese and they go down with each sale. Skip it and count by hand. Both work.'],
        ['q' => 'What happens to my data if I stop paying?', 'a' => 'You can still log in and download everything as Excel. It is your data.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => 'POS, inventory & true daily profit for food businesses'])
        <meta name="description" content="iPOSa is the register, inventory and daily profit ledger for cafés, burger stands, milk tea shops and carinderias in the Philippines.">
    </head>
    <body class="font-sans antialiased">
        <x-page-loader />
        {{-- Nav --}}
        <header class="sticky top-0 z-40 border-b border-transparent bg-ink-50/80 backdrop-blur dark:bg-ink-950/80">
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <a href="{{ route('home') }}" class="text-xl"><x-brand-mark /></a>
                <div class="hidden items-center gap-8 text-sm text-ink-600 md:flex dark:text-ink-400">
                    <a href="#how" class="hover:text-ink-900 dark:hover:text-white">How it works</a>
                    <a href="#inventory" class="hover:text-ink-900 dark:hover:text-white">Inventory</a>
                    <a href="#pricing" class="hover:text-ink-900 dark:hover:text-white">Pricing</a>
                    <a href="#faq" class="hover:text-ink-900 dark:hover:text-white">FAQ</a>
                </div>
                <div class="flex items-center gap-1 sm:gap-2">
                    <x-theme-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary py-2">Open app</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-quiet hidden sm:inline-flex">Log in</a>
                        <a href="{{ route('register') }}" class="btn-primary py-2">Start free</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main>
            {{-- Hero --}}
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute -right-40 top-10 size-[36rem] rounded-full bg-brand-500/10 blur-3xl"></div>
                <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 pb-20 pt-14 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:pb-28 lg:pt-20">
                    <div>
                        <p class="eyebrow">For cafés · burger stands · milk tea · carinderias</p>
                        <h1 class="mt-5 font-display text-5xl leading-[1.02] tracking-tight text-ink-900 sm:text-6xl lg:text-7xl dark:text-white">
                            Know your real profit <span class="italic text-brand-500 dark:text-brand-300">before you lock up.</span>
                        </h1>
                        <p class="mt-6 max-w-xl text-lg leading-relaxed text-ink-600 dark:text-ink-300">
                            Ring up orders, count your oil and mayo in 60 seconds at closing, and see tonight's true profit in pesos. Ingredients, bulk, expenses, all of it. No more guessing from the cash drawer.
                        </p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('register') }}" class="btn-primary px-6 py-3.5 text-base">Start 14-day free trial <x-icon name="arrow-right" class="size-4" /></a>
                            <a href="#how" class="btn-ghost px-6 py-3.5 text-base">See a day in iPOSa</a>
                        </div>
                        <p class="mt-5 text-sm text-ink-500">No card needed · Works on your phone or tablet · Export to Excel any time</p>
                    </div>
                    <div class="flex justify-center lg:justify-end">
                        <x-closing-receipt class="rotate-[1.5deg]" />
                    </div>
                </div>
            </section>

            {{-- A day in three screens --}}
            <section id="how" class="border-t border-ink-200 py-20 lg:py-28 dark:border-white/[0.06]">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="max-w-2xl">
                        <p class="eyebrow">How it works</p>
                        <h2 class="mt-3 font-display text-4xl text-ink-900 sm:text-5xl dark:text-white">One day, three screens.</h2>
                        <p class="mt-4 text-ink-600 dark:text-ink-400">The rush is for your cashier. The closing is for whoever's last out. The number is for you.</p>
                    </div>

                    <ol class="mt-14 grid gap-6 lg:grid-cols-3">
                        {{-- Rush --}}
                        <li class="surface flex flex-col p-6">
                            <p class="num text-xs text-ink-500">12:30 PM · the rush</p>
                            <h3 class="mt-2 text-lg font-semibold">Tap, tap, charge.</h3>
                            <p class="mt-1 text-sm text-ink-500">Big colored tiles, sizes as separate taps. Cash, GCash or Maya. Linked buns and patties go down by themselves.</p>
                            <div class="mt-6 grid grid-cols-2 gap-2" aria-hidden="true">
                                @foreach ([['Cheeseburger', '₱109', 'border-l-brand-400 bg-brand-400/10'], ['Tapsilog', '₱139', 'border-l-rose-400 bg-rose-400/10'], ['Fries · Large', '₱89', 'border-l-yellow-300 bg-yellow-300/10'], ['Iced Tea · 22oz', '₱60', 'border-l-sky-400 bg-sky-400/10']] as [$tileName, $tilePrice, $tileTone])
                                    <div class="rounded-xl border-l-4 p-3 {{ $tileTone }}">
                                        <p class="text-sm font-semibold">{{ $tileName }}</p>
                                        <p class="num mt-2 text-sm">{{ $tilePrice }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </li>

                        {{-- Close --}}
                        <li class="surface flex flex-col p-6">
                            <p class="num text-xs text-ink-500">9:40 PM · closing</p>
                            <h3 class="mt-2 text-lg font-semibold">Count by eye. Done in a minute.</h3>
                            <p class="mt-1 text-sm text-ink-500">Only the bottles and tubs. The oil was 5 bottles, one is half gone, so type 4.5. No recipes or grams to weigh.</p>
                            <div class="mt-6 space-y-2" aria-hidden="true">
                                @foreach ([['Cooking oil', '4.5', 'was 5'], ['Mayonnaise', '1.75', 'was 2'], ['Ketchup', '3.5', 'was 5']] as [$bulkName, $bulkCount, $bulkWas])
                                    <div class="flex items-center gap-3 rounded-xl border border-ink-200 p-2.5 dark:border-white/[0.07]">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium">{{ $bulkName }}</p>
                                            <p class="num text-[11px] text-ink-500">{{ $bulkWas }}</p>
                                        </div>
                                        <span class="flex size-8 items-center justify-center rounded-lg bg-ink-100 dark:bg-white/[0.06]"><x-icon name="minus" class="size-4" /></span>
                                        <span class="num w-10 text-center font-semibold">{{ $bulkCount }}</span>
                                        <span class="flex size-8 items-center justify-center rounded-lg bg-ink-100 dark:bg-white/[0.06]"><x-icon name="plus" class="size-4" /></span>
                                    </div>
                                @endforeach
                            </div>
                        </li>

                        {{-- Number --}}
                        <li class="surface flex flex-col p-6">
                            <p class="num text-xs text-ink-500">9:42 PM · the number</p>
                            <h3 class="mt-2 text-lg font-semibold">True profit, with the math shown.</h3>
                            <p class="mt-1 text-sm text-ink-500">Sales minus ingredients, minus what the audit says you used, minus today's expenses. Tap any line to see what's behind it.</p>
                            <div class="mt-6 rounded-xl border border-ink-200 p-4 dark:border-white/[0.07]" aria-hidden="true">
                                <p class="eyebrow">True profit today</p>
                                <p class="num mt-2 text-3xl font-semibold tracking-tight">₱10,010.00</p>
                                <div class="num mt-4 space-y-1 text-xs text-ink-500">
                                    <div class="flex justify-between"><span>Sales</span><span>₱18,420.00</span></div>
                                    <div class="flex justify-between"><span>Ingredients</span><span>−₱5,730.00</span></div>
                                    <div class="flex justify-between"><span>Bulk used</span><span>−₱380.00</span></div>
                                    <div class="flex justify-between"><span>Expenses</span><span>−₱2,300.00</span></div>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            {{-- Inventory model: the differentiator --}}
            <section id="inventory" class="border-t border-ink-200 bg-white py-20 lg:py-28 dark:border-white/[0.06] dark:bg-ink-900/40">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                        <div>
                            <p class="eyebrow">Inventory that matches your kitchen</p>
                            <h2 class="mt-3 font-display text-4xl text-ink-900 sm:text-5xl dark:text-white">Buns count themselves. <span class="italic">Mayo doesn't.</span></h2>
                            <p class="mt-4 text-ink-600 dark:text-ink-400">Most POS apps want you to weigh every gram of sauce, or they skip it and your profit is fiction. iPOSa splits your stock the way a real kitchen works.</p>
                        </div>
                        <div class="divide-y divide-ink-200 border-y border-ink-200 dark:divide-white/[0.07] dark:border-white/[0.07]">
                            @foreach ([
                                ['01', 'Menu items', 'What customers buy. Burgers, drinks by size, add-ons. Each size gets its own cost, price and margin, like your Excel matrix.', 'On the register'],
                                ['02', 'Pieces', 'Buns, patties, cheese slices, cups. Link them to a menu item and every sale deducts them automatically.', 'Auto-deducted'],
                                ['03', 'Bulk & liquids', 'Oil, mayo, ketchup, syrup, LPG. Counted by eye at closing in decimals. The drop becomes today\'s cost.', 'Closing audit'],
                            ] as [$number, $kind, $description, $tag])
                                <div class="grid gap-3 py-7 sm:grid-cols-[48px_1fr_auto] sm:gap-6">
                                    <span class="num text-sm text-brand-600 dark:text-brand-300">{{ $number }}</span>
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ $kind }}</h3>
                                        <p class="mt-1 text-sm leading-relaxed text-ink-600 dark:text-ink-400">{{ $description }}</p>
                                    </div>
                                    <span class="pill h-fit self-start bg-ink-100 text-ink-600 dark:bg-white/[0.06] dark:text-ink-300">{{ $tag }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- Excel --}}
            <section class="border-t border-ink-200 py-20 lg:py-28 dark:border-white/[0.06]">
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                    <div class="order-2 lg:order-1">
                        <div class="surface overflow-hidden" aria-hidden="true">
                            <div class="flex items-center gap-2 border-b border-ink-200 px-4 py-2.5 text-xs text-ink-500 dark:border-white/[0.07]">
                                <x-icon name="download" class="size-3.5" /> ledger-september-2026.xlsx
                            </div>
                            <table class="num w-full text-xs">
                                <thead class="text-ink-500">
                                    <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                        <th class="px-4 py-2 text-left font-medium">Date</th>
                                        <th class="px-3 py-2 text-right font-medium">Sales</th>
                                        <th class="px-3 py-2 text-right font-medium">COGS</th>
                                        <th class="px-3 py-2 text-right font-medium">Expenses</th>
                                        <th class="px-4 py-2 text-right font-medium">Net</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                                    @foreach ([['Sep 13', '23,410', '7,591', '—', '15,819'], ['Sep 14', '12,880', '4,302', '—', '8,578'], ['Sep 15', '13,920', '4,665', '23,340', '(14,085)'], ['Sep 16', '15,100', '5,049', '—', '10,051'], ['Sep 17', '16,240', '5,378', '1,920', '8,942']] as [$ledgerDate, $ledgerSales, $ledgerCogs, $ledgerExpenses, $ledgerNet])
                                        <tr>
                                            <td class="px-4 py-2">{{ $ledgerDate }}</td>
                                            <td class="px-3 py-2 text-right">{{ $ledgerSales }}</td>
                                            <td class="px-3 py-2 text-right text-ink-500">{{ $ledgerCogs }}</td>
                                            <td class="px-3 py-2 text-right text-ink-500">{{ $ledgerExpenses }}</td>
                                            <td @class(['px-4 py-2 text-right font-semibold', 'text-loss-600 dark:text-loss-400' => str_starts_with($ledgerNet, '(')])>{{ $ledgerNet }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="order-1 lg:order-2">
                        <p class="eyebrow">Made for Excel people</p>
                        <h2 class="mt-3 font-display text-4xl text-ink-900 sm:text-5xl dark:text-white">Your spreadsheet, without the typing.</h2>
                        <ul class="mt-6 space-y-4 text-ink-600 dark:text-ink-400">
                            <li class="flex gap-3"><x-icon name="upload" class="mt-0.5 size-5 text-brand-500" /> <span><strong class="font-semibold text-ink-900 dark:text-white">Bring your menu sheet in.</strong> Sizes, cost price and selling price. Margins are calculated for you.</span></li>
                            <li class="flex gap-3"><x-icon name="receipt" class="mt-0.5 size-5 text-brand-500" /> <span><strong class="font-semibold text-ink-900 dark:text-white">Log expenses like a row.</strong> Date, category, amount. Rent, Meralco, wages, ice, and equipment installments.</span></li>
                            <li class="flex gap-3"><x-icon name="download" class="mt-0.5 size-5 text-brand-500" /> <span><strong class="font-semibold text-ink-900 dark:text-white">Take it all back out.</strong> One click to .xlsx, on every plan. You're never locked in.</span></li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- Pricing --}}
            <section id="pricing" class="border-t border-ink-200 py-20 lg:py-28 dark:border-white/[0.06]">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="eyebrow">Pricing</p>
                        <h2 class="mt-3 font-display text-4xl text-ink-900 sm:text-5xl dark:text-white">Less than a sack of rice a month.</h2>
                        <p class="mt-4 text-ink-600 dark:text-ink-400">One branch per account. 14 days free on either plan, no card needed. Cancel from settings, no calls.</p>
                    </div>
                    <div class="mx-auto mt-14 grid max-w-4xl gap-6 md:grid-cols-2">
                        @foreach ($plans as $plan)
                            <div @class(['relative flex flex-col rounded-3xl border p-8', 'border-brand-400 bg-brand-400/[0.05]' => $plan['featured'], 'border-ink-200 dark:border-white/[0.08]' => ! $plan['featured']])>
                                @if ($plan['featured'])
                                    <span class="pill absolute -top-3 left-8 bg-brand-400 py-1 text-ink-950">Most owners pick this</span>
                                @endif
                                <h3 class="text-lg font-semibold">{{ $plan['name'] }}</h3>
                                <p class="text-sm text-ink-500">{{ $plan['for'] }}</p>
                                <p class="mt-6"><span class="num text-5xl font-semibold tracking-tight">₱{{ number_format($plan['price']) }}</span><span class="text-ink-500"> / month</span></p>
                                <ul class="mt-8 flex-1 space-y-3 text-sm">
                                    @foreach ($plan['features'] as $feature)
                                        <li class="flex gap-3"><x-icon name="check" class="size-5 text-gain-500" /> {{ $feature }}</li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('register') }}" @class(['mt-8', 'btn-primary py-3' => $plan['featured'], 'btn-ghost py-3' => ! $plan['featured']])>Start free trial</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- FAQ --}}
            <section id="faq" class="border-t border-ink-200 py-20 lg:py-28 dark:border-white/[0.06]">
                <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.7fr_1.3fr]">
                    <div>
                        <p class="eyebrow">Questions owners ask</p>
                        <h2 class="mt-3 font-display text-4xl text-ink-900 dark:text-white">Before you switch.</h2>
                    </div>
                    <div class="divide-y divide-ink-200 border-y border-ink-200 dark:divide-white/[0.07] dark:border-white/[0.07]">
                        @foreach ($faqs as $faq)
                            <details class="group py-5 [&_summary::-webkit-details-marker]:hidden">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-6 font-medium">
                                    {{ $faq['q'] }}
                                    <x-icon name="plus" class="size-5 text-ink-400 transition group-open:rotate-45" />
                                </summary>
                                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-ink-600 dark:text-ink-400">{{ $faq['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Final CTA --}}
            <section class="border-t border-ink-200 py-24 dark:border-white/[0.06]">
                <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
                    <h2 class="font-display text-5xl leading-tight text-ink-900 sm:text-6xl dark:text-white">Tonight, see the <span class="italic text-brand-500 dark:text-brand-300">real</span> number.</h2>
                    <p class="mt-5 text-ink-600 dark:text-ink-400">Set up takes about as long as a lunch break. Import your menu, invite your cashier, and do your first closing audit tonight.</p>
                    <a href="{{ route('register') }}" class="btn-primary mt-8 px-7 py-4 text-base">Start 14-day free trial <x-icon name="arrow-right" class="size-4" /></a>
                </div>
            </section>
        </main>

        <footer class="border-t border-ink-200 dark:border-white/[0.06]">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 text-sm text-ink-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex items-center gap-3"><x-brand-mark class="text-base text-ink-900 dark:text-white" /> <span>Made in the Philippines for shops that close at ten.</span></div>
                <div class="flex gap-6">
                    <a href="#pricing" class="hover:text-ink-900 dark:hover:text-white">Pricing</a>
                    <a href="#faq" class="hover:text-ink-900 dark:hover:text-white">FAQ</a>
                    <a href="{{ route('login') }}" class="hover:text-ink-900 dark:hover:text-white">Log in</a>
                </div>
            </div>
        </footer>
    </body>
</html>
