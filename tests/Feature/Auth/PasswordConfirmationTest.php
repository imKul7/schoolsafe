<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Str;

function createPasswordConfirmationTestSchool(): School
{
    $identifier = Str::lower(Str::random(10));

    return School::query()->create([
        'code' => "PASSWORD-{$identifier}",
        'name' => 'Sekolah Pengujian Konfirmasi Password',
        'npsn' => null,
        'email' => "password-confirmation-{$identifier}@example.test",
        'phone' => '081234567890',
        'address' => 'Alamat sekolah pengujian',
        'city' => 'Depok',
        'province' => 'Jawa Barat',
        'logo_path' => null,
        'timezone' => 'Asia/Jakarta',
        'is_active' => true,
    ]);
}

test('confirm password screen can be rendered', function () {
    $school = createPasswordConfirmationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertStatus(200);
});

test('password can be confirmed', function () {
    $school = createPasswordConfirmationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $school = createPasswordConfirmationTestSchool();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});
