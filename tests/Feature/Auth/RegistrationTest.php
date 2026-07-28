<?php

test('public registration screen is not available', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('public registration submission is not available', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();

    $this->assertGuest();
});
