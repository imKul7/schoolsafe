<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_persons', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('school_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('full_name');
            $table->string('identity_number', 30)->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            $table->string('photo_path')->nullable();

            /*
             * Status pendaftaran wajah:
             * not_registered, registered, needs_update
             */
            $table
                ->string('face_status', 30)
                ->default('not_registered');

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'school_id',
                'identity_number',
            ]);

            $table->index([
                'school_id',
                'is_active',
            ]);

            $table->index([
                'school_id',
                'face_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_persons');
    }
};
