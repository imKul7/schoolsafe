<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('school_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table
                ->foreignId('school_class_id')
                ->constrained('school_classes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('student_number', 50);
            $table->string('nisn', 20)->nullable();
            $table->string('full_name');
            $table->string('gender', 20);
            $table->date('date_of_birth')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'school_id',
                'student_number',
            ]);

            $table->unique('nisn');

            $table->index([
                'school_id',
                'school_class_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};