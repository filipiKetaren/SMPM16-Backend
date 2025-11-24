<?php
// database/migrations/2024_01_02_create_classes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // 7A, 7B, 8A, etc
            $table->tinyInteger('grade_level'); // 7, 8, 9
            $table->foreignId('academic_year_id')->constrained()->onDelete('restrict');
            $table->string('homeroom_teacher', 100)->nullable();
            $table->tinyInteger('capacity')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('grade_level');
            $table->index('academic_year_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
