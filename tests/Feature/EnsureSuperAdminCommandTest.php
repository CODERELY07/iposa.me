<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a verified platform operator', function () {
    $this->artisan('app:ensure-super-admin', ['--email' => 'Ops@Example.com', '--password' => 'a-long-secret-123'])
        ->assertSuccessful();

    $operator = User::where('email', 'ops@example.com')->sole();
    expect($operator->isSuperAdmin())->toBeTrue()
        ->and($operator->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-long-secret-123', $operator->password))->toBeTrue();
});

it('keeps the existing password on later deploys', function () {
    $this->artisan('app:ensure-super-admin', ['--email' => 'ops@example.com', '--password' => 'a-long-secret-123']);
    $this->artisan('app:ensure-super-admin', ['--email' => 'ops@example.com', '--password' => 'another-secret-456'])->assertSuccessful();

    expect(Hash::check('a-long-secret-123', User::where('email', 'ops@example.com')->sole()->password))->toBeTrue();
});

it('refuses a short password', function () {
    $this->artisan('app:ensure-super-admin', ['--email' => 'ops@example.com', '--password' => 'short'])->assertFailed();

    expect(User::count())->toBe(0);
});

it('skips quietly when no email is configured', function () {
    $this->artisan('app:ensure-super-admin')->assertSuccessful();

    expect(User::count())->toBe(0);
});
