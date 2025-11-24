<?php
// database/migrations/2024_01_01_add_role_and_profile_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tambahkan kolom baru sesuai ERD
            $table->string('username')->unique()->after('id');
            $table->string('full_name')->after('username');
            $table->enum('role', ['super_admin', 'admin_attendance', 'admin_finance'])->default('admin_finance');
            $table->string('phone')->nullable()->after('role');
            $table->string('photo')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('photo');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip')->nullable()->after('last_login_at');

            // Hapus kolom name yang lama
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kembalikan ke struktur semula
            $table->string('name')->after('id');

            $table->dropColumn([
                'username',
                'full_name',
                'role',
                'phone',
                'photo',
                'is_active',
                'last_login_at',
                'last_login_ip'
            ]);
        });
    }
};
