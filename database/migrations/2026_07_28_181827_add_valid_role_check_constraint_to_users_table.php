<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            <<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT chk_users_role
            CHECK (
                role IN (
                    'super_admin',
                    'school_admin',
                    'gate_officer',
                    'teacher',
                    'parent'
                )
            )
            SQL,
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT chk_users_role',
        );
    }
};