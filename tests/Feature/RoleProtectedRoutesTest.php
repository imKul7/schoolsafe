<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\User;

/**
 * Membuat pengguna aktif dengan hubungan sekolah
 * yang sesuai terhadap role-nya.
 */
function createRoleProtectedRouteUser(
    string $role,
): User {
    if ($role === User::ROLE_SUPER_ADMIN) {
        return User::factory()->create([
            'school_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    $school = School::factory()->create([
        'is_active' => true,
    ]);

    return User::factory()->create([
        'school_id' => $school->id,
        'role' => $role,
        'is_active' => true,
    ]);
}

dataset('allowed school module routes', [
    'school admin accesses gate verification' => [
        User::ROLE_SCHOOL_ADMIN,
        'gate.face-verification.index',
    ],

    'gate officer accesses gate verification' => [
        User::ROLE_GATE_OFFICER,
        'gate.face-verification.index',
    ],

    'school admin accesses students' => [
        User::ROLE_SCHOOL_ADMIN,
        'students.index',
    ],

    'teacher accesses students' => [
        User::ROLE_TEACHER,
        'students.index',
    ],

    'school admin accesses pickup persons' => [
        User::ROLE_SCHOOL_ADMIN,
        'pickup-persons.index',
    ],

    'gate officer accesses pickup persons' => [
        User::ROLE_GATE_OFFICER,
        'pickup-persons.index',
    ],

    'teacher accesses pickup persons' => [
        User::ROLE_TEACHER,
        'pickup-persons.index',
    ],
]);

dataset('forbidden school module routes', [
    'teacher cannot access gate verification' => [
        User::ROLE_TEACHER,
        'gate.face-verification.index',
        'Akun tidak memiliki izin mengelola transaksi gerbang.',
    ],

    'parent cannot access gate verification' => [
        User::ROLE_PARENT,
        'gate.face-verification.index',
        'Akun tidak memiliki izin mengelola transaksi gerbang.',
    ],

    'super admin cannot access gate verification' => [
        User::ROLE_SUPER_ADMIN,
        'gate.face-verification.index',
        'Akun tidak memiliki izin mengelola transaksi gerbang.',
    ],

    'gate officer cannot access students' => [
        User::ROLE_GATE_OFFICER,
        'students.index',
        'Anda tidak memiliki izin untuk melakukan tindakan ini.',
    ],

    'parent cannot access students' => [
        User::ROLE_PARENT,
        'students.index',
        'Anda tidak memiliki izin untuk melakukan tindakan ini.',
    ],

    'super admin cannot access students' => [
        User::ROLE_SUPER_ADMIN,
        'students.index',
        'Anda tidak memiliki izin untuk melakukan tindakan ini.',
    ],

    'parent cannot access pickup persons' => [
        User::ROLE_PARENT,
        'pickup-persons.index',
        'Anda tidak memiliki izin melihat data penjemput.',
    ],

    'super admin cannot access pickup persons' => [
        User::ROLE_SUPER_ADMIN,
        'pickup-persons.index',
        'Anda tidak memiliki izin melihat data penjemput.',
    ],
]);

dataset('guest protected routes', [
    'gate verification' => [
        'gate.face-verification.index',
    ],

    'students' => [
        'students.index',
    ],

    'pickup persons' => [
        'pickup-persons.index',
    ],
]);

test(
    'allowed roles can access protected school module routes',
    function (
        string $role,
        string $routeName,
    ): void {
        $user =
            createRoleProtectedRouteUser(
                $role,
            );

        $this
            ->actingAs($user)
            ->get(route($routeName))
            ->assertOk();
    },
)->with('allowed school module routes');

test(
    'unsupported roles receive the scoped forbidden response',
    function (
        string $role,
        string $routeName,
        string $message,
    ): void {
        $user =
            createRoleProtectedRouteUser(
                $role,
            );

        $this
            ->actingAs($user)
            ->getJson(route($routeName))
            ->assertForbidden()
            ->assertExactJson([
                'message' => $message,
            ]);
    },
)->with('forbidden school module routes');

test(
    'guests receive the authentication response before role authorization',
    function (string $routeName): void {
        $this
            ->getJson(route($routeName))
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Silakan masuk untuk melanjutkan.',
            ]);
    },
)->with('guest protected routes');
