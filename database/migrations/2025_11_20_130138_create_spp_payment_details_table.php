<?php
// database/migrations/2024_01_06_create_spp_payment_details_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spp_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('spp_payments')->onDelete('cascade');
            $table->tinyInteger('month'); // 1-12
            $table->smallInteger('year');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'month', 'year']);
            $table->index('payment_id');
            $table->index(['month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_payment_details');
    }
};
