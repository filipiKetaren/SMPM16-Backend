<?php
// database/migrations/2024_01_03_create_students_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('nis', 20)->unique();
            $table->string('full_name', 100);
            $table->foreignId('class_id')->constrained()->onDelete('restrict');
            $table->string('photo')->nullable();
            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
            $table->text('address')->nullable();
            $table->string('parent_phone', 20)->nullable();
            $table->string('parent_email', 100)->nullable();
            $table->enum('status', ['active', 'alumni', 'moved', 'dropped'])->default('active');
            $table->date('admission_date');
            $table->timestamps();

            $table->index('nis');
            $table->index('full_name');
            $table->index('class_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
