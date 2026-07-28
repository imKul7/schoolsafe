<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped(
            'User-school constraints require a MySQL-compatible database.',
        );
    }
});

test('database rejects school users without a school', function () {
    $user = User::factory()->schoolAdmin()->create();

    $originalSchoolId = $user->school_id;

    expect(
        fn () => $user->update([
            'school_id' => null,
        ]),
    )->toThrow(QueryException::class);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
        'school_id' => $originalSchoolId,
    ]);
});

test('database rejects super administrators linked to a school', function () {
    $school = School::factory()->create();

    $user = User::factory()
        ->superAdmin()
        ->create();

    expect(
        fn () => $user->update([
            'school_id' => $school->id,
        ]),
    )->toThrow(QueryException::class);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => User::ROLE_SUPER_ADMIN,
        'school_id' => null,
    ]);
});

test('database rejects changing a school user into a super administrator without clearing the school', function () {
    $user = User::factory()
        ->schoolAdmin()
        ->create();

    expect(
        fn () => $user->update([
            'role' => User::ROLE_SUPER_ADMIN,
        ]),
    )->toThrow(QueryException::class);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
    ]);
});

test('school with linked users cannot be deleted', function () {
    $school = School::factory()->create();

    $user = User::factory()->create([
        'school_id' => $school->id,
    ]);

    expect(
        fn () => $school->delete(),
    )->toThrow(QueryException::class);

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'school_id' => $school->id,
    ]);
});
