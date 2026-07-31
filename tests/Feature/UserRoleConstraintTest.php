<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('database rejects unsupported user roles', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped(
            'User role constraint requires a MySQL-compatible database.',
        );
    }

    $user = User::factory()->create();

    expect(
        fn () => DB::table('users')
            ->where('id', $user->id)
            ->update([
                'role' => 'staff',
            ]),
    )->toThrow(QueryException::class);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => User::ROLE_SCHOOL_ADMIN,
    ]);
});
