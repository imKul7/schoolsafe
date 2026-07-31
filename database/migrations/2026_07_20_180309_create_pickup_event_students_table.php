<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'pickup_event_students',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Transaksi induk
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId('pickup_event_id')
                    ->constrained('pickup_events')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Siswa
                |--------------------------------------------------------------------------
                |
                | Student dapat menjadi null apabila data siswa dihapus.
                | Snapshot data siswa tetap dipertahankan untuk audit.
                |
                */

                $table
                    ->foreignId('student_id')
                    ->nullable()
                    ->constrained('students')
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Snapshot siswa
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'student_name',
                    150,
                );

                $table
                    ->string(
                        'student_number',
                        50,
                    )
                    ->nullable();

                $table
                    ->string(
                        'class_name',
                        100,
                    )
                    ->nullable();

                $table
                    ->string(
                        'academic_year',
                        30,
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Snapshot relasi penjemput
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'relationship_type',
                        30,
                    )
                    ->nullable();

                $table
                    ->boolean('is_primary')
                    ->default(false);

                /*
                |--------------------------------------------------------------------------
                | Status penyerahan siswa
                |--------------------------------------------------------------------------
                |
                | released  = siswa telah diserahkan
                | cancelled = penyerahan dibatalkan
                |
                */

                $table
                    ->string(
                        'status',
                        20,
                    )
                    ->default('released')
                    ->index();

                $table
                    ->timestamp('released_at')
                    ->useCurrent()
                    ->index();

                $table
                    ->timestamp('cancelled_at')
                    ->nullable();

                $table
                    ->text('cancellation_reason')
                    ->nullable();

                $table->timestamps();

                /*
                 * Satu siswa tidak boleh tercatat dua kali dalam
                 * satu transaksi penjemputan.
                 */
                $table->unique(
                    [
                        'pickup_event_id',
                        'student_id',
                    ],
                    'pickup_event_student_unique',
                );

                $table->index(
                    [
                        'student_id',
                        'released_at',
                    ],
                    'pickup_event_students_student_date_index',
                );

                $table->index(
                    [
                        'pickup_event_id',
                        'status',
                    ],
                    'pickup_event_students_event_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'pickup_event_students',
        );
    }
};
