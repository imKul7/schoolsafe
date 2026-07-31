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
            'pickup_events',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Tenant sekolah
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId('school_id')
                    ->constrained('schools')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Penjemput
                |--------------------------------------------------------------------------
                |
                | Data utama dapat menjadi null apabila penjemput dihapus
                | permanen. Snapshot nama dan telepon tetap disimpan.
                |
                */

                $table
                    ->foreignId('pickup_person_id')
                    ->nullable()
                    ->constrained('pickup_persons')
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Dasar verifikasi
                |--------------------------------------------------------------------------
                |
                | Nilai unique mencegah satu hasil verifikasi wajah dipakai
                | untuk mengonfirmasi lebih dari satu transaksi penjemputan.
                |
                | Kolom dibuat nullable agar tahap selanjutnya dapat mendukung
                | verifikasi manual tanpa mengubah struktur tabel.
                |
                */

                $table
                    ->foreignId(
                        'face_verification_attempt_id',
                    )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'pickup_person_face_verification_attempts',
                    )
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Petugas yang mengonfirmasi
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId(
                        'confirmed_by_user_id',
                    )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Idempotency
                |--------------------------------------------------------------------------
                |
                | UUID dibuat frontend untuk mencegah klik ganda, retry browser,
                | atau dua request identik membuat transaksi ganda.
                |
                */

                $table
                    ->uuid('idempotency_key')
                    ->unique();

                /*
                |--------------------------------------------------------------------------
                | Metode dan status transaksi
                |--------------------------------------------------------------------------
                |
                | verification_method:
                | - face
                | - manual
                |
                | status:
                | - confirmed
                | - cancelled
                |
                */

                $table
                    ->string(
                        'verification_method',
                        20,
                    )
                    ->default('face')
                    ->index();

                $table
                    ->string(
                        'status',
                        20,
                    )
                    ->default('confirmed')
                    ->index();

                /*
                |--------------------------------------------------------------------------
                | Snapshot penjemput
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'pickup_person_name',
                    150,
                );

                $table
                    ->string(
                        'pickup_person_phone',
                        30,
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Snapshot hasil pencocokan
                |--------------------------------------------------------------------------
                |
                | Data ini mempermudah riwayat tanpa selalu membaca ulang tabel
                | audit verifikasi wajah.
                |
                */

                $table
                    ->string(
                        'verification_result',
                        30,
                    )
                    ->default('match');

                $table
                    ->decimal(
                        'similarity_score',
                        5,
                        4,
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'similarity_threshold',
                        5,
                        4,
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'candidate_margin',
                        5,
                        4,
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Waktu transaksi
                |--------------------------------------------------------------------------
                */

                $table
                    ->timestamp('confirmed_at')
                    ->useCurrent()
                    ->index();

                $table
                    ->timestamp('cancelled_at')
                    ->nullable()
                    ->index();

                /*
                |--------------------------------------------------------------------------
                | Catatan dan audit request
                |--------------------------------------------------------------------------
                */

                $table
                    ->text('notes')
                    ->nullable();

                $table
                    ->string(
                        'ip_address',
                        45,
                    )
                    ->nullable();

                $table
                    ->text('user_agent')
                    ->nullable();

                /*
                 * Metadata tidak boleh berisi embedding wajah.
                 */
                $table
                    ->json('metadata')
                    ->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Index pencarian riwayat
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'school_id',
                        'status',
                        'confirmed_at',
                    ],
                    'pickup_events_school_status_date_index',
                );

                $table->index(
                    [
                        'school_id',
                        'pickup_person_id',
                        'confirmed_at',
                    ],
                    'pickup_events_school_person_date_index',
                );

                $table->index(
                    [
                        'school_id',
                        'confirmed_by_user_id',
                        'confirmed_at',
                    ],
                    'pickup_events_school_user_date_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'pickup_events',
        );
    }
};
