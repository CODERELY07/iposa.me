<?php

use App\Models\Order;

it('serves an installable app manifest with icons', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['short_name'])->toBe('iPOSa')
        ->and($manifest['display'])->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('src')->all())->toContain('/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.png');

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('links the favicon, manifest and app icons on every page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('/favicon.svg', false)
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee('/apple-touch-icon.png', false);
});

it('serves the service worker, never cached, with the offline page precached', function () {
    $response = $this->get(route('pwa.service-worker'));

    $response->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

    expect($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->getContent())->toContain('"/offline.html"')
        ->and($response->getContent())->not->toContain('__VERSION__')
        ->and($response->getContent())->not->toContain('__PRECACHE__')
        ->and(public_path('offline.html'))->toBeFile();
});

it('keeps the time an offline sale happened when it syncs later', function () {
    $owner = shopOwner();
    $menu = demoMenu($owner);
    $soldAt = now()->subHours(3)->startOfMinute();

    $this->actingAs($owner)
        ->postJson(route('pos.orders.store'), orderPayload([[$menu['water500'], 1]], 'gcash') + ['offline_created_at' => $soldAt->toIso8601String()])
        ->assertCreated();

    expect(Order::withoutGlobalScopes()->sole()->paid_at->equalTo($soldAt))->toBeTrue();
});

it('refuses an offline sale time far in the past or in the future', function () {
    $owner = shopOwner();
    $menu = demoMenu($owner);

    foreach ([now()->subDays(8), now()->addHour()] as $when) {
        $this->actingAs($owner)
            ->postJson(route('pos.orders.store'), orderPayload([[$menu['water500'], 1]], 'gcash') + ['offline_created_at' => $when->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offline_created_at');
    }
});

it('does not double-charge an offline sale that is synced twice', function () {
    $owner = shopOwner();
    $menu = demoMenu($owner);
    $payload = orderPayload([[$menu['water500'], 2]], 'gcash') + ['offline_created_at' => now()->subMinutes(30)->toIso8601String()];

    $this->actingAs($owner)->postJson(route('pos.orders.store'), $payload)->assertCreated();
    $this->actingAs($owner)->postJson(route('pos.orders.store'), $payload)->assertOk();

    expect(Order::withoutGlobalScopes()->count())->toBe(1)
        ->and((float) $menu['water']->refresh()->on_hand)->toBe(28.0);
});
