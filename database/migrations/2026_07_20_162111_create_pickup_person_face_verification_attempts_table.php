<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE_NAME =
        'pickup_person_face_verification_attempts';

    private const TABLE_COMMENT =
        'schoolsafe:migration:2026_07_20_162111';

    /*
    |--------------------------------------------------------------------------
    | Nama foreign key
    |--------------------------------------------------------------------------
    |
    | Nama dibuat eksplisit dan pendek agar tidak melewati batas identifier
    | MySQL sebesar 64 karakter.
    |
    */

    private const FOREIGN_SCHOOL =
        'face_attempt_school_fk';

    private const FOREIGN_PICKUP_PERSON =
        'face_attempt_person_fk';

    private const FOREIGN_VERIFIED_BY_USER =
        'face_attempt_user_fk';

    /*
    |--------------------------------------------------------------------------
    | Nama index
    |--------------------------------------------------------------------------
    */

    private const INDEX_RESULT =
        'face_attempt_result_idx';

    private const INDEX_SCHOOL_RESULT_TIME =
        'face_attempt_school_result_time_idx';

    private const INDEX_SCHOOL_PERSON_TIME =
        'face_attempt_school_person_time_idx';

    private const INDEX_SCHOOL_USER_TIME =
        'face_attempt_school_user_time_idx';

    public function up(): void
    {
        /*
         * Jangan menandai migration sebagai berhasil apabila terdapat tabel
         * lama atau tabel parsial dari proses migration yang pernah gagal.
         */
        if (
            Schema::hasTable(
                self::TABLE_NAME,
            )
        ) {
            if (
                $this
                    ->tableWasCreatedByThisMigration()
            ) {
                return;
            }

            throw new RuntimeException(
                sprintf(
                    'Tabel [%s] sudah ada tetapi tidak memiliki penanda migration ini. '
                    .'Periksa tabel tersebut dan hapus hanya apabila merupakan tabel '
                    .'kosong sisa migration yang gagal.',
                    self::TABLE_NAME,
                ),
            );
        }

        try {
            Schema::create(
                self::TABLE_NAME,
                function (
                    Blueprint $table,
                ): void {
                    $table->id();

                    /*
                    |--------------------------------------------------------------------------
                    | Tenant sekolah
                    |--------------------------------------------------------------------------
                    */

                    $table->foreignId(
                        'school_id',
                    );

                    $table
                        ->foreign(
                            'school_id',
                            self::FOREIGN_SCHOOL,
                        )
                        ->references('id')
                        ->on('schools')
                        ->cascadeOnDelete();

                    /*
                    |--------------------------------------------------------------------------
                    | Penjemput yang diverifikasi
                    |--------------------------------------------------------------------------
                    |
                    | Nullable karena penjemput dapat terhapus permanen,
                    | sedangkan catatan audit tetap harus disimpan.
                    |
                    */

                    $table
                        ->foreignId(
                            'pickup_person_id',
                        )
                        ->nullable();

                    $table
                        ->foreign(
                            'pickup_person_id',
                            self::FOREIGN_PICKUP_PERSON,
                        )
                        ->references('id')
                        ->on('pickup_persons')
                        ->nullOnDelete();

                    /*
                    |--------------------------------------------------------------------------
                    | Petugas yang melakukan verifikasi
                    |--------------------------------------------------------------------------
                    */

                    $table
                        ->foreignId(
                            'verified_by_user_id',
                        )
                        ->nullable();

                    $table
                        ->foreign(
                            'verified_by_user_id',
                            self::FOREIGN_VERIFIED_BY_USER,
                        )
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();

                    /*
                    |--------------------------------------------------------------------------
                    | Hasil verifikasi
                    |--------------------------------------------------------------------------
                    |
                    | Nilai yang digunakan antara lain:
                    |
                    | - match
                    | - no_match
                    | - ambiguous
                    | - no_candidates
                    | - low_quality
                    | - liveness_failed
                    | - model_mismatch
                    |
                    */

                    $table->string(
                        'result',
                        30,
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Nilai pencocokan
                    |--------------------------------------------------------------------------
                    */

                    $table
                        ->decimal(
                            'similarity_score',
                            5,
                            4,
                        )
                        ->nullable();

                    $table->decimal(
                        'similarity_threshold',
                        5,
                        4,
                    );

                    $table
                        ->decimal(
                            'candidate_margin',
                            5,
                            4,
                        )
                        ->nullable();

                    $table
                        ->unsignedInteger(
                            'candidate_count',
                        )
                        ->default(0);

                    /*
                    |--------------------------------------------------------------------------
                    | Kualitas dan liveness
                    |--------------------------------------------------------------------------
                    */

                    $table
                        ->decimal(
                            'quality_score',
                            5,
                            4,
                        )
                        ->nullable();

                    $table
                        ->boolean(
                            'liveness_passed',
                        )
                        ->default(false);

                    $table
                        ->decimal(
                            'live_score',
                            5,
                            4,
                        )
                        ->nullable();

                    $table
                        ->decimal(
                            'real_score',
                            5,
                            4,
                        )
                        ->nullable();

                    /*
                    |--------------------------------------------------------------------------
                    | Informasi model biometrik
                    |--------------------------------------------------------------------------
                    */

                    $table->string(
                        'model_name',
                        100,
                    );

                    $table
                        ->string(
                            'model_version',
                            50,
                        )
                        ->nullable();

                    $table->unsignedSmallInteger(
                        'embedding_dimension',
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Sumber pengambilan
                    |--------------------------------------------------------------------------
                    */

                    $table
                        ->string(
                            'capture_method',
                            20,
                        )
                        ->default('camera');

                    /*
                    |--------------------------------------------------------------------------
                    | Audit request
                    |--------------------------------------------------------------------------
                    */

                    $table
                        ->string(
                            'ip_address',
                            45,
                        )
                        ->nullable();

                    $table
                        ->text(
                            'user_agent',
                        )
                        ->nullable();

                    /*
                     * Metadata dapat menyimpan data kualitas, challenge,
                     * dan session binding, tetapi tidak boleh menyimpan
                     * embedding atau descriptor wajah.
                     */
                    $table
                        ->json(
                            'metadata',
                        )
                        ->nullable();

                    /*
                    |--------------------------------------------------------------------------
                    | Waktu kejadian
                    |--------------------------------------------------------------------------
                    */

                    $table
                        ->timestamp(
                            'occurred_at',
                        )
                        ->useCurrent();

                    $table->timestamps();

                    /*
                    |--------------------------------------------------------------------------
                    | Index pencarian
                    |--------------------------------------------------------------------------
                    |
                    | Seluruh nama index dibuat eksplisit agar tetap berada
                    | di bawah batas identifier MySQL.
                    |
                    */

                    $table->index(
                        'result',
                        self::INDEX_RESULT,
                    );

                    $table->index(
                        [
                            'school_id',
                            'result',
                            'occurred_at',
                        ],
                        self::INDEX_SCHOOL_RESULT_TIME,
                    );

                    $table->index(
                        [
                            'school_id',
                            'pickup_person_id',
                            'occurred_at',
                        ],
                        self::INDEX_SCHOOL_PERSON_TIME,
                    );

                    $table->index(
                        [
                            'school_id',
                            'verified_by_user_id',
                            'occurred_at',
                        ],
                        self::INDEX_SCHOOL_USER_TIME,
                    );
                },
            );

            /*
             * Berikan penanda setelah seluruh kolom, constraint,
             * dan index selesai dibuat.
             */
            $this
                ->markTableAsCreatedByThisMigration();
        } catch (Throwable $exception) {
            /*
             * DDL MySQL tidak selalu transaksional.
             *
             * Jika CREATE TABLE berhasil tetapi pemasangan constraint atau
             * index gagal, tabel dapat tertinggal dalam keadaan parsial.
             * Karena tabel dipastikan belum ada sebelum proses dimulai,
             * tabel parsial aman dibersihkan.
             */
            try {
                if (
                    Schema::hasTable(
                        self::TABLE_NAME,
                    )
                    && ! $this
                        ->tableWasCreatedByThisMigration()
                ) {
                    Schema::dropIfExists(
                        self::TABLE_NAME,
                    );
                }
            } catch (Throwable) {
                /*
                 * Pertahankan exception pertama sebagai penyebab utama
                 * kegagalan migration.
                 */
            }

            throw $exception;
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable(
                self::TABLE_NAME,
            )
        ) {
            return;
        }

        /*
         * Jangan menghapus tabel lama yang tidak dibuat oleh migration ini.
         */
        if (
            ! $this
                ->tableWasCreatedByThisMigration()
        ) {
            return;
        }

        Schema::dropIfExists(
            self::TABLE_NAME,
        );
    }

    /**
     * Memberikan penanda pada tabel yang selesai dibuat.
     */
    private function markTableAsCreatedByThisMigration(): void
    {
        if (
            DB::connection()
                ->getDriverName()
            !== 'mysql'
        ) {
            return;
        }

        $tableName = str_replace(
            '`',
            '``',
            self::TABLE_NAME,
        );

        $tableComment = str_replace(
            "'",
            "''",
            self::TABLE_COMMENT,
        );

        DB::statement(
            sprintf(
                "ALTER TABLE `%s` COMMENT = '%s'",
                $tableName,
                $tableComment,
            ),
        );
    }

    /**
     * Memastikan tabel dibuat oleh migration ini sebelum rollback.
     */
    private function tableWasCreatedByThisMigration(): bool
    {
        /*
         * Project SchoolSafe menggunakan MySQL.
         *
         * Driver lain tidak memiliki mekanisme TABLE_COMMENT yang seragam.
         * Pada driver selain MySQL, tabel yang tersedia diperlakukan sebagai
         * tabel yang dikelola migration ini.
         */
        if (
            DB::connection()
                ->getDriverName()
            !== 'mysql'
        ) {
            return true;
        }

        $databaseName =
            DB::connection()
                ->getDatabaseName();

        if (
            ! is_string(
                $databaseName,
            )
            || trim(
                $databaseName,
            ) === ''
        ) {
            return false;
        }

        $tableInformation =
            DB::selectOne(
                <<<'SQL'
                    SELECT TABLE_COMMENT AS table_comment
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ?
                      AND TABLE_NAME = ?
                    LIMIT 1
                SQL,
                [
                    $databaseName,
                    self::TABLE_NAME,
                ],
            );

        if (
            ! is_object(
                $tableInformation,
            )
        ) {
            return false;
        }

        $tableComment =
            $tableInformation
                ->table_comment
            ?? null;

        return
            is_string(
                $tableComment,
            )
            && hash_equals(
                self::TABLE_COMMENT,
                $tableComment,
            );
    }
};
