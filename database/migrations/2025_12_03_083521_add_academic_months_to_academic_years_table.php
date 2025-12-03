<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            // Tambahkan field untuk bulan akademik
            $table->integer('start_month')->after('start_date')->default(7); // Juli
            $table->integer('end_month')->after('end_date')->default(6); // Juni tahun berikutnya
            $table->boolean('allow_partial_payment')->default(true);
        });

        Schema::table('spp_payment_details', function (Blueprint $table) {
            // Tambahkan field untuk menandai status pembayaran
            $table->boolean('is_paid')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropColumn(['start_month', 'end_month', 'allow_partial_payment']);
        });

        Schema::table('spp_payment_details', function (Blueprint $table) {
            $table->dropColumn('is_paid');
        });
    }
};
