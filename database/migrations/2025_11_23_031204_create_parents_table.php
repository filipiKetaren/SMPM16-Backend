<?php
// database/migrations/2025_11_22_000000_create_parents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->string('full_name', 100);
            $table->string('phone', 20);
            $table->enum('role', ['parent'])->default('parent');
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('fcm_token')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('username');
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('parents');
    }
};
