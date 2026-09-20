@use('App\Enums\BusinessStatus')

@php
    $owner = $business->owner;
@endphp

<x-app-layout :title="'Edit '.$business->business_name">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-8">
        <a href="{{ route('super_admin.businesses.show', $business) }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> {{ $business->business_name }}
        </a>

        <x-page-header eyebrow="Platform" :title="'Edit '.$business->business_name"
            description="Fix anything the shop got wrong at sign-up, and set their subscription by hand. Orders, sales and audits are derived from real records, so they aren't editable here." />

        <form method="POST" action="{{ route('super_admin.businesses.update', $business) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="surface space-y-4 p-6">
                <div>
                    <h2 class="font-semibold">The shop</h2>
                    <p class="mt-1 text-sm text-ink-500">Used on the register, receipts and exports.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="business_name">Business name</label>
                        <input id="business_name" name="business_name" type="text" required maxlength="255" class="field" value="{{ old('business_name', $business->business_name) }}">
                        <x-input-error :messages="$errors->get('business_name')" class="mt-1" />
                    </div>

                    <div>
                        <label class="field-label" for="business_type">Type</label>
                        <select id="business_type" name="business_type" class="field">
                            @foreach ($businessTypes as $type)
                                <option value="{{ $type }}" @selected(old('business_type', $business->business_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('business_type')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="field-label" for="address">Address</label>
                        <input id="address" name="address" type="text" maxlength="255" class="field" value="{{ old('address', $business->address) }}">
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>

                    <div>
                        <label class="field-label" for="tin">TIN</label>
                        <input id="tin" name="tin" type="text" maxlength="30" class="field num" value="{{ old('tin', $business->tin) }}">
                        <x-input-error :messages="$errors->get('tin')" class="mt-1" />
                    </div>

                    <div>
                        <label class="field-label" for="receipt_footer">Receipt footer</label>
                        <input id="receipt_footer" name="receipt_footer" type="text" maxlength="120" class="field" value="{{ old('receipt_footer', $business->receipt_footer) }}">
                        <x-input-error :messages="$errors->get('receipt_footer')" class="mt-1" />
                    </div>
                </div>
            </section>

            <section class="surface space-y-4 p-6">
                <div>
                    <h2 class="font-semibold">Subscription</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        The price is what this shop pays, not the plan's list price — lower it to give a discount.
                        Suspending has its own button on the shop's page, because it needs a reason and your password.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="plan">Plan</label>
                        <select id="plan" name="plan" class="field">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->key }}" @selected(old('plan', $business->plan) === $plan->key)>
                                    {{ $plan->name }} · ₱{{ number_format((float) $plan->price) }}{{ $plan->isArchived() ? ' (archived)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('plan')" class="mt-1" />
                    </div>

                    <div>
                        <label class="field-label" for="plan_price">Price for this shop (₱ / month)</label>
                        <input id="plan_price" name="plan_price" type="number" inputmode="decimal" min="0" max="999999" step="0.01" required class="field num" value="{{ old('plan_price', $business->monthlyPrice()) }}">
                        <x-input-error :messages="$errors->get('plan_price')" class="mt-1" />
                    </div>

                    <div>
                        <label class="field-label" for="status">Status</label>
                        <select id="status" name="status" class="field">
                            @foreach ($editableStatuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $business->status->value) === $status)>{{ BusinessStatus::from($status)->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="field-label" for="start_date">Started</label>
                            <input id="start_date" name="start_date" type="date" class="field num" value="{{ old('start_date', $business->start_date?->format('Y-m-d')) }}">
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>
                        <div>
                            <label class="field-label" for="due_date">Due</label>
                            <input id="due_date" name="due_date" type="date" class="field num" value="{{ old('due_date', $business->due_date?->format('Y-m-d')) }}">
                            <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <p class="text-xs text-ink-500">
                    Signed up {{ $business->created_at?->format('M j, Y') }} · order and sales figures come from their real records and can't be edited.
                </p>
            </section>

            <section class="surface space-y-4 p-6">
                <div>
                    <h2 class="font-semibold">Owner</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        @if ($owner)
                            Fixing a mistyped email is the usual reason. A new email starts unverified — verify it for them under
                            <a href="{{ route('super_admin.verifications') }}" class="font-medium text-ink-900 hover:underline dark:text-white">Verifications</a>.
                        @else
                            This shop has no owner account linked.
                        @endif
                    </p>
                </div>

                @if ($owner)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label" for="owner_name">Name</label>
                            <input id="owner_name" name="owner_name" type="text" required maxlength="255" class="field" value="{{ old('owner_name', $owner->name) }}">
                            <x-input-error :messages="$errors->get('owner_name')" class="mt-1" />
                        </div>

                        <div>
                            <label class="field-label" for="owner_email">Email</label>
                            <input id="owner_email" name="owner_email" type="email" required maxlength="255" class="field" value="{{ old('owner_email', $owner->email) }}">
                            <p class="mt-1 text-xs text-ink-500">{{ $owner->email_verified_at ? 'Verified '.$owner->email_verified_at->format('M j, Y') : 'Not verified yet.' }}</p>
                            <x-input-error :messages="$errors->get('owner_email')" class="mt-1" />
                        </div>
                    </div>
                @endif
            </section>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('super_admin.businesses.show', $business) }}" class="btn-quiet">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Saving…">Save changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
