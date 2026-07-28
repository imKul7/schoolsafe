<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Membuat sekolah aktif khusus pengujian autentikasi.
 *
 * @param array<string, mixed> $attributes
 */
function createAuthenticationTestSchool(
    array $attributes = [],
): School {
    $identifier = Str::lower(Str::random(10));

    return School::query()->create(
        array_merge(
            [
                'code' => "TEST-{$identifier}",
                'name' => 'Sekolah Pengujian',
                'npsn' => null,
                'email' => "school-{$identifier}@example.test",
                'phone' => '081234567890',
                'address' => 'Alamat sekolah pengujian',
                'city' => 'Depok',
                'province' => 'Jawa Barat',
                'logo_path' => null,
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
            $attributes,
        ),
    );
}

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk();
});

test('active users from active schools can authenticate', function () {
    $school = createAuthenticationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);

    $response->assertRedirect(
        route('dashboard', absolute: false),
    );
});

test('users cannot authenticate with an invalid password', function () {
    $school = createAuthenticationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('active users can logout', function () {
    $school = createAuthenticationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post('/logout');

    $this->assertGuest();

    $response->assertRedirect('/');
});

test('inactive users cannot authenticate', function () {
    $school = createAuthenticationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
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
    $school = createAuthenticationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
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

test('users from inactive schools are logged out', function () {
    $school = createAuthenticationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $school->update([
        'is_active' => false,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));

    $response->assertSessionHas(
        'status',
        'Sekolah Anda telah dinonaktifkan.',
    );

    $this->assertGuest();
});

test('school users without a school are logged out', function () {
    $user = User::factory()->create([
        'school_id' => null,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect(route('login'));

    $response->assertSessionHas(
        'status',
        'Akun Anda belum terhubung dengan sekolah.',
    );

    $this->assertGuest();
});

test('active super administrators can access the application without a school', function () {
    $user = User::factory()->create([
        'school_id' => null,
        'role' => User::ROLE_SUPER_ADMIN,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();

    $this->assertAuthenticatedAs($user);
});
