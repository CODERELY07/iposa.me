<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'business_name' => "Kape't Burger",
        'business_type' => 'Burger & fast food',
        'phone' => '0917 123 4567',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('businesses', [
        'business_name' => "Kape't Burger",
        'status' => 'trial',
        'phone' => '+639171234567',
    ]);
});

test('registration needs a Philippine mobile number', function () {
    $details = [
        'name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        'business_name' => "Kape't Burger", 'business_type' => 'Burger & fast food',
    ];

    $this->post('/register', $details)->assertSessionHasErrors('phone');
    $this->post('/register', $details + ['phone' => '12345'])->assertSessionHasErrors('phone');

    $this->assertGuest();
});
