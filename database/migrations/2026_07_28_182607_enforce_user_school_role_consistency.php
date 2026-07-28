<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['school_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table
                ->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->restrictOnDelete();
        });

        DB::statement(
            <<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT chk_users_school_role_consistency
            CHECK (
                (
                    role = 'super_admin'
                    AND school_id IS NULL
                )
                OR
                (
                    role <> 'super_admin'
                    AND school_id IS NOT NULL
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
            'ALTER TABLE users DROP CONSTRAINT chk_users_school_role_consistency',
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['school_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table
                ->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->nullOnDelete();
        });
    }
};
