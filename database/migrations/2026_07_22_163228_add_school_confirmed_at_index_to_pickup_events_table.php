<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE =
        'pickup_events';

    private const INDEX =
        'pickup_events_school_confirmed_at_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            throw new RuntimeException(
                sprintf(
                    'Tabel [%s] tidak ditemukan.',
                    self::TABLE,
                ),
            );
        }

        if (
            ! Schema::hasColumns(
                self::TABLE,
                [
                    'school_id',
                    'confirmed_at',
                ],
            )
        ) {
            throw new RuntimeException(
                sprintf(
                    'Kolom school_id atau confirmed_at tidak ditemukan pada tabel [%s].',
                    self::TABLE,
                ),
            );
        }

        if ($this->indexExists()) {
            return;
        }

        Schema::table(
            self::TABLE,
            static function (
                Blueprint $table,
            ): void {
                $table->index(
                    [
                        'school_id',
                        'confirmed_at',
                    ],
                    self::INDEX,
                );
            },
        );
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable(
                self::TABLE,
            )
            || ! $this->indexExists()
        ) {
            return;
        }

        Schema::table(
            self::TABLE,
            static function (
                Blueprint $table,
            ): void {
                $table->dropIndex(
                    self::INDEX,
                );
            },
        );
    }

    private function indexExists(): bool
    {
        $databaseName =
            trim(
                (string) DB::connection()
                    ->getDatabaseName(),
            );

        if ($databaseName === '') {
            throw new RuntimeException(
                'Nama database tidak tersedia saat memeriksa index.',
            );
        }

        return DB::table(
            'information_schema.STATISTICS',
        )
            ->where(
                'TABLE_SCHEMA',
                $databaseName,
            )
            ->where(
                'TABLE_NAME',
                self::TABLE,
            )
            ->where(
                'INDEX_NAME',
                self::INDEX,
            )
            ->exists();
    }
};
