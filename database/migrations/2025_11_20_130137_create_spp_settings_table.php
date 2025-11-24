<?php
// database/migrations/2024_01_04_create_spp_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('grade_level'); // 7, 8, 9
            $table->decimal('monthly_amount', 10, 2);
            $table->tinyInteger('due_date')->default(10); // 1-31
            $table->boolean('late_fee_enabled')->default(false);
            $table->enum('late_fee_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('late_fee_amount', 10, 2)->nullable();
            $table->tinyInteger('late_fee_start_day')->nullable(); // hari setelah jatuh tempo
            $table->timestamps();

            $table->unique(['academic_year_id', 'grade_level']);
            $table->index('academic_year_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_settings');
    }
};
