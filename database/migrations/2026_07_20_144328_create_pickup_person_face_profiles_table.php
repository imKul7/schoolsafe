<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migration.
     */
    public function up(): void
    {
        Schema::create(
            'pickup_person_face_profiles',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('school_id')
                    ->constrained('schools')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('pickup_person_id')
                    ->unique()
                    ->constrained('pickup_persons')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('registered_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                 * Nilai embedding akan dienkripsi oleh model.
                 * Gunakan longText karena hasil enkripsi lebih panjang
                 * daripada JSON aslinya.
                 */
                $table
                    ->longText('embedding')
                    ->nullable();

                $table
                    ->unsignedSmallInteger(
                        'embedding_dimension',
                    )
                    ->nullable();

                $table
                    ->string('model_name', 100)
                    ->nullable();

                $table
                    ->string('model_version', 50)
                    ->nullable();

                $table
                    ->decimal(
                        'quality_score',
                        5,
                        4,
                    )
                    ->nullable();

                $table
                    ->boolean('liveness_passed')
                    ->default(false);

                $table
                    ->string('capture_method', 20)
                    ->nullable();

                $table
                    ->char('photo_sha256', 64)
                    ->nullable();

                $table
                    ->string('status', 20)
                    ->default('registered')
                    ->index();

                $table
                    ->unsignedInteger(
                        'registration_revision',
                    )
                    ->default(1);

                $table
                    ->string('consent_version', 50)
                    ->nullable();

                $table
                    ->timestamp('consented_at')
                    ->nullable();

                $table
                    ->timestamp('registered_at')
                    ->nullable();

                $table
                    ->timestamp('invalidated_at')
                    ->nullable();

                $table
                    ->timestamp('revoked_at')
                    ->nullable();

                $table
                    ->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'school_id',
                    'status',
                ]);
            },
        );
    }

    /**
     * Batalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'pickup_person_face_profiles',
        );
    }
};