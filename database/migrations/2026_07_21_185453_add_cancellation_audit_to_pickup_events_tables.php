<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'pickup_events',
            function (Blueprint $table): void {
                /*
                 * Pengguna yang membatalkan transaksi.
                 *
                 * Nullable agar histori tetap tersedia ketika
                 * akun pengguna dihapus.
                 */
                $table
                    ->foreignId(
                        'cancelled_by_user_id',
                    )
                    ->nullable()
                    ->after(
                        'confirmed_by_user_id',
                    )
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                 * Alasan pembatalan wajib diisi melalui
                 * request pembatalan.
                 */
                $table
                    ->string(
                        'cancellation_reason',
                        1000,
                    )
                    ->nullable()
                    ->after('cancelled_at');

                $table->index(
                    [
                        'school_id',
                        'cancelled_by_user_id',
                        'cancelled_at',
                    ],
                    'pickup_events_school_cancel_user_date_idx',
                );
            },
        );

        Schema::table(
            'pickup_event_students',
            function (Blueprint $table): void {
                /*
                 * Digunakan ketika hanya siswa tertentu
                 * yang dibatalkan dari transaksi.
                 */
                $table
                    ->foreignId(
                        'cancelled_by_user_id',
                    )
                    ->nullable()
                    ->after('cancelled_at')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'pickup_event_id',
                        'cancelled_by_user_id',
                        'cancelled_at',
                    ],
                    'pickup_students_event_cancel_user_date_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'pickup_event_students',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'pickup_students_event_cancel_user_date_idx',
                );

                $table->dropConstrainedForeignId(
                    'cancelled_by_user_id',
                );
            },
        );

        Schema::table(
            'pickup_events',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'pickup_events_school_cancel_user_date_idx',
                );

                $table->dropConstrainedForeignId(
                    'cancelled_by_user_id',
                );

                $table->dropColumn(
                    'cancellation_reason',
                );
            },
        );
    }
};