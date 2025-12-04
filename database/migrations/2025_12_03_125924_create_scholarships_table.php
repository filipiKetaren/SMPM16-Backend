<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('scholarship_name'); // Contoh: "Beasiswa Prestasi", "Beasiswa Yatim", dll.
            $table->enum('type', ['full', 'partial'])->default('full');
            $table->decimal('discount_percentage', 5, 2)->default(100)->comment('100% untuk full scholarship');
            $table->decimal('discount_amount', 10, 2)->nullable()->comment('Discount amount jika fixed');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->text('description')->nullable();
$table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->onDelete('set null');
            $table->string('sponsor')->nullable();
            $table->text('requirements')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('scholarships');
    }
};
