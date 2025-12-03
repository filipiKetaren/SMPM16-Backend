<?php
// database/migrations/xxxx_create_report_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('report_type'); // spp, savings, financial_summary
            $table->string('report_format'); // excel, pdf
            $table->string('period_type'); // monthly, yearly
            $table->integer('year');
            $table->integer('month')->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['user_id', 'report_type']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_logs');
    }
};
