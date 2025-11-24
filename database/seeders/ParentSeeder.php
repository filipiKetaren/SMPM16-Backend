<?php
// database/seeders/ParentSeeder.php

namespace Database\Seeders;

use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        $parents = [
            [
                'username' => 'parent_ahmad',
                'email' => 'ahmad.parent@email.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Bapak Ahmad Fauzi',
                'phone' => '081234567001',
                'role' => 'parent',
                'is_active' => true,
            ],
            [
                'username' => 'parent_siti',
                'email' => 'siti.parent@email.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Ibu Siti Rahayu',
                'phone' => '081234567002',
                'role' => 'parent',
                'is_active' => true,
            ],
            [
                'username' => 'parent_budi',
                'email' => 'budi.parent@email.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Bapak Budi Santoso',
                'phone' => '081234567003',
                'role' => 'parent',
                'is_active' => true,
            ],
            [
                'username' => 'parent_dewi',
                'email' => 'dewi.parent@email.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Ibu Dewi Lestari',
                'phone' => '081234567004',
                'role' => 'parent',
                'is_active' => true,
            ],
            [
                'username' => 'parent_joko',
                'email' => 'joko.parent@email.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Bapak Joko Prasetyo',
                'phone' => '081234567005',
                'role' => 'parent',
                'is_active' => true,
            ],
        ];

        foreach ($parents as $parentData) {
            $parent = ParentModel::create($parentData);

            // Hubungkan parent dengan siswa berdasarkan email
            $studentEmail = $parentData['email'];
            $student = Student::where('parent_email', $studentEmail)->first();

            if ($student) {
                $parent->students()->attach($student->id, [
                    'relationship' => str_contains($parentData['full_name'], 'Ibu') ? 'mother' : 'father'
                ]);
            }
        }

        $this->command->info('Data orang tua berhasil ditambahkan: ' . count($parents) . ' orang tua');
    }
}
