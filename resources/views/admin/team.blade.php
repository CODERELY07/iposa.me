@php
    $plan = $business->planDetails();
    $initials = fn (string $name) => \Illuminate\Support\Str::of($name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('');
@endphp

<x-app-layout title="Team">
    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Team" title="Who can use the register"
            :description="$plan['staff_limit'] === null
                ? 'Your '.$plan['name'].' plan includes unlimited staff. Cashiers only see the register, the closing audit and their own orders.'
                : 'Your '.$plan['name'].' plan includes '.$plan['staff_limit'].' staff ('.$seatsLeft.' left). Cashiers only see the register, the closing audit and their own orders.'" />

        <section class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
            @foreach ($members as $member)
                @php($lastActivity = $lastSeen[$member->id] ?? null)
                <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                    <span class="flex size-10 items-center justify-center rounded-full bg-ink-100 text-sm font-semibold uppercase dark:bg-white/[0.07]">{{ $initials($member->name) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $member->name }} @if ($member->is(auth()->user()))<span class="text-xs font-normal text-ink-400">(you)</span>@endif</p>
                        <p class="truncate text-xs text-ink-500">{{ $member->email }}</p>
                    </div>
                    <span @class(['pill', 'bg-brand-400/15 text-brand-700 dark:text-brand-300' => $member->isAdmin(), 'bg-ink-100 text-ink-600 dark:bg-white/[0.07] dark:text-ink-300' => ! $member->isAdmin()])>{{ $member->isAdmin() ? 'Owner' : 'Cashier' }}</span>
                    <p class="w-28 text-right text-xs text-ink-500">{{ $lastActivity ? 'Active '.\Illuminate\Support\Carbon::createFromTimestamp($lastActivity)->diffForHumans() : 'Not logged in yet' }}</p>
                    @if ($member->isStaff())
                        <div class="flex items-center gap-1">
                            <button type="button" @click="$dispatch('set-password', {{ $member->id }})" class="btn-quiet px-2 text-xs">Set password</button>
                            <form method="POST" action="{{ route('admin.team.resend', $member) }}">
                                @csrf
                                <button type="submit" class="btn-quiet px-2 text-xs" data-loading-text="Sending…">Resend invite</button>
                            </form>
                            <form method="POST" action="{{ route('admin.team.destroy', $member) }}" onsubmit="return confirm('Remove {{ e(addslashes($member->name)) }}? They are logged out right away. Their past orders keep their name.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-quiet px-2 text-xs text-loss-600 dark:text-loss-400" data-loading-text="Removing…">Remove</button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('admin.team.password', $member) }}"
                            x-data="{ open: {{ $errors->staffPassword->any() && old('staff_id') == $member->id ? 'true' : 'false' }} }"
                            @set-password.window="open = $event.detail === {{ $member->id }}" x-show="open" x-cloak
                            class="flex w-full flex-wrap items-end gap-2 rounded-xl bg-ink-50 p-3 dark:bg-white/[0.03]">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="staff_id" value="{{ $member->id }}">
                            <div class="min-w-[12rem] flex-1">
                                <label class="field-label" for="staff_password_{{ $member->id }}">New password for {{ $member->name }}</label>
                                <input id="staff_password_{{ $member->id }}" name="password" type="text" minlength="8" required autocomplete="off" class="field num" placeholder="At least 8 characters">
                                @if ($errors->staffPassword->any() && old('staff_id') == $member->id)
                                    <p class="mt-1 text-xs text-loss-600 dark:text-loss-400">{{ $errors->staffPassword->first('password') }}</p>
                                @endif
                            </div>
                            <button type="button" @click="open = false" class="btn-ghost">Cancel</button>
                            <button type="submit" class="btn-primary" data-loading-text="Saving…">Save password</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </section>

        @if ($seatsLeft === 0)
            <div class="rounded-2xl border border-brand-400/40 bg-brand-400/10 p-4 text-sm">
                All {{ $plan['staff_limit'] }} staff seats are used. <a href="{{ route('admin.settings') }}#billing" class="font-semibold hover:underline">Switch to Negosyo</a> for unlimited staff.
            </div>
        @else
            <form method="POST" action="{{ route('admin.team.store') }}" class="surface grid gap-3 p-5 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                @csrf
                <p class="text-sm font-semibold sm:col-span-4">Add a cashier <span class="font-normal text-ink-500">· they get an email to set their password, or set one yourself</span></p>
                <div>
                    <label class="field-label" for="invite_name">Name</label>
                    <input id="invite_name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" class="field" placeholder="Cashier's name">
                </div>
                <div>
                    <label class="field-label" for="invite_email">Email</label>
                    <input id="invite_email" name="email" type="email" value="{{ old('email') }}" required class="field" placeholder="name@email.com">
                </div>
                <div>
                    <label class="field-label" for="invite_password">Password <span class="text-ink-400">(optional)</span></label>
                    <input id="invite_password" name="password" type="text" minlength="8" autocomplete="off" class="field num" placeholder="Leave empty to email an invite">
                </div>
                <button type="submit" class="btn-primary" data-loading-text="Adding…">Add cashier</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.team.permissions') }}" class="surface p-6">
            @csrf
            @method('PATCH')
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold">What cashiers can do</h2>
                <button type="submit" class="btn-primary py-2" data-loading-text="Saving…">Save</button>
            </div>
            <ul class="mt-4 divide-y divide-ink-100 dark:divide-white/[0.06]">
                @foreach ($permissions as $key => $permission)
                    <li x-data="{ on: @js((bool) $permissionValues[$key]) }" class="flex items-center justify-between gap-4 py-3.5">
                        <div>
                            <p class="text-sm font-medium">{{ $permission['label'] }}</p>
                            <p class="text-xs text-ink-500">{{ $permission['hint'] }}</p>
                        </div>
                        <input type="hidden" name="permissions[{{ $key }}]" :value="on ? 1 : 0">
                        <button type="button" role="switch" :aria-checked="on.toString()" @click="on = ! on" aria-label="{{ $permission['label'] }}"
                            :class="on ? 'bg-brand-400' : 'bg-ink-200 dark:bg-white/10'" class="relative h-6 w-11 shrink-0 rounded-full transition">
                            <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="absolute left-0 top-0.5 size-5 rounded-full bg-white shadow transition"></span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </form>
    </div>
</x-app-layout>
