<?php

use App\Enums\BusinessStatus;
use App\Models\DailySummary;
use App\Models\Order;
use App\Reports\DailyBrief;
use App\Services\Sms\SmsGateClient;
use App\Services\Summary\DailySummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.sms_gate.url' => 'http://phone.test:8080/message',
        'services.sms_gate.username' => 'gate',
        'services.sms_gate.password' => 'secret',
    ]);

    $this->owner = shopOwner(['business_name' => 'Kape Republic']);
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
    $this->cashier->forceFill(['name' => 'Ana'])->save();
    // The demo oil (5 bottles) is below the default alert; keep it out of the way unless a test wants it.
    $this->menu['oil']->update(['low_threshold' => 1]);
    $this->enable = fn (array $overrides = []) => $this->business->update(['settings' => ['sms_summary' => $overrides + [
        'enabled' => true, 'time' => '22:00', 'numbers' => ['+639171234567'],
    ]]]);
    $this->at = fn (string $time) => CarbonImmutable::parse(today()->toDateString().' '.$time);
    $this->voidOne = function () {
        $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]]))->assertCreated();
        $order = Order::withoutGlobalScopes()->latest('id')->first();
        $this->cashier->forceFill([])->save();
        $this->business->update(['settings' => array_replace($this->business->settings ?? [], ['cashier_permissions' => ['void_orders' => true]])]);
        $this->actingAs($this->cashier->fresh())->post(route('pos.orders.void', $order))->assertRedirect();

        return $order;
    };
});

it('normalises Philippine mobile numbers and refuses the rest', function () {
    expect(SmsGateClient::normalizeNumber('0917 123 4567'))->toBe('+639171234567')
        ->and(SmsGateClient::normalizeNumber('639171234567'))->toBe('+639171234567')
        ->and(SmsGateClient::normalizeNumber('+63 917-123-4567'))->toBe('+639171234567')
        ->and(SmsGateClient::normalizeNumber('12345'))->toBeNull()
        ->and(SmsGateClient::normalizeNumber('+14155550123'))->toBeNull();
});

it('writes only the voids and the low stock, in plain text', function () {
    ($this->voidOne)();
    $this->menu['bun']->update(['on_hand' => 4]);

    $text = app(DailyBrief::class)->build($this->business, now());

    expect($text)->toContain('Kape Republic')
        ->and($text)->toContain('VOIDS: 1 = PHP 109 (Ana 1).')
        ->and($text)->toContain('LOW STOCK: Burger bun 4 pc')
        ->and($text)->not->toContain('₱')
        ->and(strlen($text))->toBeLessThan(460);
});

it('says so plainly when nothing was voided and nothing is low', function () {
    expect(app(DailyBrief::class)->build($this->business, now()))
        ->toContain('VOIDS: none.')
        ->toContain('STOCK: nothing low.');
});

it('counts void requests that are still waiting', function () {
    $this->actingAs($this->cashier)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]]))->assertCreated();
    $this->actingAs($this->cashier)->post(route('pos.orders.void', Order::withoutGlobalScopes()->sole()))->assertRedirect();

    expect(app(DailyBrief::class)->build($this->business, now()))->toContain('1 void request waiting.');
});

it('never mixes in another shop\'s voids or stock', function () {
    $other = shopOwner();
    $otherMenu = demoMenu($other);
    $otherMenu['bun']->update(['on_hand' => 1]);
    $this->actingAs(cashierOf($other))->postJson(route('pos.orders.store'), orderPayload([[$otherMenu['burgerRegular'], 1]]))->assertCreated();
    $this->actingAs($other)->post(route('pos.orders.void', Order::withoutGlobalScopes()->sole()))->assertRedirect();
    auth()->logout();

    expect(app(DailyBrief::class)->build($this->business, now()))
        ->toContain('VOIDS: none.')
        ->toContain('STOCK: nothing low.');
});

it('texts the owner once, after the chosen time, through the gateway', function () {
    Http::fake(['phone.test:8080/*' => Http::response(['id' => 'x'], 202)]);
    ($this->enable)();
    $service = app(DailySummaryService::class);

    expect($service->sendDue(($this->at)('21:59')))->toBe(0)
        ->and($service->sendDue(($this->at)('22:00')))->toBe(1)
        ->and($service->sendDue(($this->at)('22:01')))->toBe(0);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://phone.test:8080/message'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('gate:secret'))
        && $request['phoneNumbers'] === ['+639171234567']
        && str_contains($request['textMessage']['text'], 'Kape Republic'));

    $summary = DailySummary::withoutGlobalScopes()->sole();
    expect($summary->wasSent())->toBeTrue()->and($summary->body)->toContain('VOIDS: none.');
});

it('retries a failed text a few minutes later, then gives up for the day', function () {
    Http::fake(['phone.test:8080/*' => Http::response('nope', 500)]);
    ($this->enable)();
    $service = app(DailySummaryService::class);
    $this->travelTo(($this->at)('22:00'));

    $service->sendDue(CarbonImmutable::now());
    expect(DailySummary::withoutGlobalScopes()->sole()->status)->toBe('failed');

    // Too soon to try again.
    $this->travel(3)->minutes();
    expect($service->sendDue(CarbonImmutable::now()))->toBe(0);
    Http::assertSentCount(1);

    foreach ([1, 2, 3] as $round) {
        $this->travel(6)->minutes();
        $service->sendDue(CarbonImmutable::now());
    }

    expect(DailySummary::withoutGlobalScopes()->sole()->attempts)->toBe(DailySummary::MAX_ATTEMPTS);
    Http::assertSentCount(DailySummary::MAX_ATTEMPTS);
});

it('does not text a shop that is switched off, has no number, owes payment or is suspended', function () {
    Http::fake();
    $service = app(DailySummaryService::class);

    ($this->enable)(['enabled' => false]);
    expect($service->sendDue(($this->at)('23:00')))->toBe(0);

    ($this->enable)(['numbers' => []]);
    expect($service->sendDue(($this->at)('23:00')))->toBe(0);

    ($this->enable)();
    $this->business->update(['status' => BusinessStatus::PastDue]);
    expect($service->sendDue(($this->at)('23:00')))->toBe(0);

    $this->business->update(['status' => BusinessStatus::Suspended]);
    expect($service->sendDue(($this->at)('23:00')))->toBe(0);

    Http::assertNothingSent();
});

it('records a failure instead of crashing when the gateway is not set up', function () {
    config(['services.sms_gate.username' => null]);
    ($this->enable)();

    app(DailySummaryService::class)->sendDue(($this->at)('22:30'));

    $summary = DailySummary::withoutGlobalScopes()->sole();
    expect($summary->status)->toBe('failed')->and($summary->error)->toContain('not set up');
});

it('runs from the scheduler command', function () {
    Http::fake(['phone.test:8080/*' => Http::response([], 202)]);
    ($this->enable)(['time' => '00:00']);

    $this->artisan('summary:send')->assertSuccessful();

    Http::assertSentCount(1);
});

it('saves the SMS settings with normalised numbers', function () {
    $this->actingAs($this->owner)->patch(route('admin.settings.sms'), [
        'sms_enabled' => '1', 'sms_time' => '21:45', 'sms_numbers' => '0917 123 4567, 09181234567',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($this->business->refresh()->smsSummary())->toBe([
        'enabled' => true, 'time' => '21:45', 'numbers' => ['+639171234567', '+639181234567'],
    ]);
});

it('refuses bad or missing numbers', function () {
    $this->actingAs($this->owner)->patch(route('admin.settings.sms'), ['sms_enabled' => '1', 'sms_time' => '21:45', 'sms_numbers' => '12345'])
        ->assertSessionHasErrors('sms_numbers');
    $this->actingAs($this->owner)->patch(route('admin.settings.sms'), ['sms_enabled' => '1', 'sms_time' => '21:45', 'sms_numbers' => ''])
        ->assertSessionHasErrors('sms_numbers');
    $this->actingAs($this->owner)->patch(route('admin.settings.sms'), ['sms_enabled' => '0', 'sms_time' => '21:45', 'sms_numbers' => '09171234567, 09171234568, 09171234569, 09171234560'])
        ->assertSessionHasErrors('sms_numbers');
});

it('sends a test text, and says when the phone cannot be reached', function () {
    ($this->enable)();

    Http::fake(['phone.test:8080/*' => Http::sequence()->push([], 202)->push([], 500)]);
    $this->actingAs($this->owner)->post(route('admin.settings.sms.test'))->assertSessionHas('status');
    Http::assertSent(fn (Request $request) => str_contains($request['textMessage']['text'], 'iPOSa test'));

    $this->actingAs($this->owner)->post(route('admin.settings.sms.test'))->assertSessionHasErrors('sms_test');
});

it('is only for owners', function () {
    $this->actingAs($this->cashier)->patch(route('admin.settings.sms'), ['sms_time' => '21:45'])->assertRedirect(route('pos'));
});

it('shows the SMS section and warns when the gateway is not connected', function () {
    config(['services.sms_gate.password' => null]);

    $this->actingAs($this->owner)->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('Daily SMS')
        ->assertSee("The SMS phone isn't connected", false);
});
