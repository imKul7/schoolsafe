<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('npsn', 20)->nullable()->unique();

            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();

            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();

            $table->string('logo_path')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
