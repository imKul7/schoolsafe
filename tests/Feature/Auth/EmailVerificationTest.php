<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

function createEmailVerificationTestSchool(): School
{
    $identifier = Str::lower(Str::random(10));

    return School::query()->create([
        'code' => "EMAIL-{$identifier}",
        'name' => 'Sekolah Pengujian Verifikasi Email',
        'npsn' => null,
        'email' => "email-verification-{$identifier}@example.test",
        'phone' => '081234567890',
        'address' => 'Alamat sekolah pengujian',
        'city' => 'Depok',
        'province' => 'Jawa Barat',
        'logo_path' => null,
        'timezone' => 'Asia/Jakarta',
        'is_active' => true,
    ]);
}

test('email verification screen can be rendered', function () {
    $school = createEmailVerificationTestSchool();

    $user = User::factory()->unverified()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $school = createEmailVerificationTestSchool();

    $user = User::factory()->unverified()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $school = createEmailVerificationTestSchool();

    $user = User::factory()->unverified()->create([
        'school_id' => $school->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'is_active' => true,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
