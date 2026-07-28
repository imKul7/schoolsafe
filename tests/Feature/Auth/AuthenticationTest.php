<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('inactive users cannot authenticate', function () {
    $user = User::factory()->create([
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated inactive users are logged out on their next request', function () {
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $user->update([
        'is_active' => false,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));

    $response->assertSessionHas(
        'status',
        'Akun Anda telah dinonaktifkan.',
    );

    $this->assertGuest();
});
