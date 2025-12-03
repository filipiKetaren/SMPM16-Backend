<?php
// database/migrations/[timestamp]_create_report_access_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('report_type'); // spp, savings, financial_summary
            $table->string('period_type'); // monthly, yearly
            $table->integer('year');
            $table->integer('month')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->index(['user_id', 'report_type']);
            $table->index(['period_type', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_access_logs');
    }
};
