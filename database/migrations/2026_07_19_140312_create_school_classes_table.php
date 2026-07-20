<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('school_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('name', 50);
            $table->unsignedTinyInteger('grade_level');
            $table->string('academic_year', 20);
            $table->string('homeroom_teacher')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'school_id',
                'name',
                'academic_year',
            ]);

            $table->index([
                'school_id',
                'grade_level',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
}; 