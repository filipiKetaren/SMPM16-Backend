<?php
// database/seeders/SuperAdminSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@smpm16.sch.id',
            'password' => Hash::make('password123'),
            'full_name' => 'Super Administrator',
            'role' => 'super_admin',
            'phone' => '+6281234567890',
            'is_active' => true,
        ]);

        // Buat sample admin keuangan
        User::create([
            'username' => 'bendahara',
            'email' => 'bendahara@smpm16.sch.id',
            'password' => Hash::make('password123'),
            'full_name' => 'Admin Bendahara',
            'role' => 'admin_finance',
            'phone' => '+6281234567891',
            'is_active' => true,
        ]);
    }
}
