<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(
        'role:'
            .User::ROLE_SCHOOL_ADMIN
            .','
            .User::ROLE_GATE_OFFICER,
    )
        ->get(
            '/_tests/role-protected',
            fn () => response()->json([
                'message' => 'Diizinkan.',
            ]),
        );

    Route::middleware('role')
        ->get(
            '/_tests/role-protected-without-roles',
            fn () => response()->json([
                'message' => 'Diizinkan.',
            ]),
        );
});

test('guest cannot access a role protected route', function () {
    $this
        ->getJson('/_tests/role-protected')
        ->assertUnauthorized();
});

test('school administrator can access an allowed route', function () {
    $user = User::factory()
        ->schoolAdmin()
        ->create();

    $this
        ->actingAs($user)
        ->getJson('/_tests/role-protected')
        ->assertOk()
        ->assertExactJson([
            'message' => 'Diizinkan.',
        ]);
});

test('gate officer can access an allowed route', function () {
    $user = User::factory()
        ->gateOfficer()
        ->create();

    $this
        ->actingAs($user)
        ->getJson('/_tests/role-protected')
        ->assertOk()
        ->assertExactJson([
            'message' => 'Diizinkan.',
        ]);
});

test('disallowed role receives forbidden response', function () {
    $user = User::factory()
        ->teacher()
        ->create();

    $this
        ->actingAs($user)
        ->getJson('/_tests/role-protected')
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'Akun tidak memiliki izin mengakses fitur ini.',
        ]);
});

test('middleware without configured roles denies access', function () {
    $user = User::factory()
        ->schoolAdmin()
        ->create();

    $this
        ->actingAs($user)
        ->getJson('/_tests/role-protected-without-roles')
        ->assertForbidden()
        ->assertExactJson([
            'message' => 'Akun tidak memiliki izin mengakses fitur ini.',
        ]);
});
