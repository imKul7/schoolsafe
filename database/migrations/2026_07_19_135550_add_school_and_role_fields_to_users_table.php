<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table
                ->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table
                ->string('role', 30)
                ->default('staff')
                ->after('email');

            $table
                ->string('phone', 30)
                ->nullable()
                ->after('role');

            $table
                ->boolean('is_active')
                ->default(true)
                ->after('phone');

            $table->index(['school_id', 'role']);
            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'role']);
            $table->dropIndex(['school_id', 'is_active']);

            $table->dropForeign(['school_id']);

            $table->dropColumn([
                'school_id',
                'role',
                'phone',
                'is_active',
            ]);
        });
    }
};
