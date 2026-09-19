<?php

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->operator = User::factory()->superAdmin()->create();
});

it('saves the business details used on receipts', function () {
    $this->actingAs($this->owner)->patch(route('admin.settings.business'), [
        'business_name' => "Kape't Burger", 'business_type' => 'Burger & fast food',
        'address' => 'Marikina City', 'tin' => '123-456-789-000', 'receipt_footer' => 'Salamat po!',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->owner->business->refresh()->receipt_footer)->toBe('Salamat po!');
});

it('turns payment methods on and off for the register', function () {
    $this->actingAs($this->owner)->patch(route('admin.settings.register'), [
        'payment_methods' => ['cash', 'card'], 'audit_reminder_time' => '22:00', 'default_low_threshold' => 15,
    ])->assertSessionHasNoErrors();

    $business = $this->owner->business->refresh();
    expect(array_map(fn ($method) => $method->value, $business->enabledPaymentMethods()))->toBe(['cash', 'card'])
        ->and($business->lowStockThreshold())->toBe(15.0);
});

it('needs at least one payment method', function () {
    $this->actingAs($this->owner)->patch(route('admin.settings.register'), [
        'payment_methods' => [], 'audit_reminder_time' => '22:00', 'default_low_threshold' => 15,
    ])->assertSessionHasErrors('payment_methods');
});

it('switches plans only when the staff fit', function () {
    User::factory()->count(4)->staffOf($this->owner->business)->create();

    $this->actingAs($this->owner)->patch(route('admin.billing.plan'), ['plan' => 'tindahan'])->assertSessionHasErrors('plan');
    expect($this->owner->business->refresh()->plan)->toBe('negosyo');
});

it('takes a payment reference and the operator confirms it', function () {
    $this->freezeTime();
    $this->owner->business->update(['status' => BusinessStatus::Trial, 'due_date' => now()->addDays(3)]);

    $this->actingAs($this->owner)->post(route('admin.billing.payments.store'), ['method' => 'gcash', 'reference' => '1009 234 567'])
        ->assertRedirect();

    $payment = SubscriptionPayment::sole();
    expect($payment->status)->toBe(SubscriptionPaymentStatus::Pending)->and((float) $payment->amount)->toBe(999.0);

    $this->actingAs($this->owner)->post(route('admin.billing.payments.store'), ['method' => 'gcash', 'reference' => 'again'])
        ->assertSessionHasErrors('reference');

    $this->actingAs($this->operator)->post(route('super_admin.payments.confirm', $payment))->assertRedirect();

    $business = $this->owner->business->refresh();
    expect($business->status)->toBe(BusinessStatus::Active)
        ->and($business->due_date->toDateString())->toBe(now()->addDays(33)->toDateString())
        ->and($payment->refresh()->status)->toBe(SubscriptionPaymentStatus::Paid);
});

it('lets the operator reject a payment with a note', function () {
    $payment = $this->owner->business->subscriptionPayments()->create(['plan' => 'negosyo', 'amount' => 999, 'method' => 'gcash', 'reference' => 'x123', 'status' => 'pending']);

    $this->actingAs($this->operator)->post(route('super_admin.payments.reject', $payment), ['note' => 'Reference not found'])->assertRedirect();

    expect($payment->refresh()->status)->toBe(SubscriptionPaymentStatus::Rejected)
        ->and($payment->review_note)->toBe('Reference not found');
});

it('blocks owners from deleting their account while they own a shop', function () {
    $this->actingAs($this->owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');

    expect(User::find($this->owner->id))->not->toBeNull();
});
