<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE =
        'pickup_persons';

    private const INDEX =
        'pickup_persons_school_active_face_idx';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(
            self::TABLE,
            function (
                Blueprint $table,
            ): void {
                $table->index(
                    [
                        'school_id',
                        'is_active',
                        'face_status',
                    ],
                    self::INDEX,
                );
            },
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            self::TABLE,
            function (
                Blueprint $table,
            ): void {
                $table->dropIndex(
                    self::INDEX,
                );
            },
        );
    }
};
