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
            'pickup_person_student',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('school_id')
                    ->constrained()
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('pickup_person_id')
                    ->constrained('pickup_persons')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('student_id')
                    ->constrained('students')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                /*
                 * father, mother, sibling,
                 * relative, driver, guardian, other
                 */
                $table->string('relationship_type', 30);

                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);

                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();

                $table->timestamps();

                $table->unique([
                    'pickup_person_id',
                    'student_id',
                ]);

                $table->index([
                    'school_id',
                    'student_id',
                    'is_active',
                ]);

                $table->index([
                    'school_id',
                    'pickup_person_id',
                    'is_active',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_person_student');
    }
};
