<?php
// database/seeders/StudentSeeder.php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            // Kelas 7A
            [
                'nis' => '202407001',
                'full_name' => 'Ahmad Fauzi',
                'class_id' => 1,
                'birth_date' => '2012-03-15',
                'gender' => 'male',
                'address' => 'Jl. Merdeka No. 123, Lubuk Pakam',
                'parent_phone' => '081234567001',
                'parent_email' => 'ahmad.parent@email.com',
                'admission_date' => '2024-07-01',
                'status' => 'active'
            ],
            [
                'nis' => '202407002',
                'full_name' => 'Siti Rahayu',
                'class_id' => 1,
                'birth_date' => '2012-05-20',
                'gender' => 'female',
                'address' => 'Jl. Sudirman No. 45, Lubuk Pakam',
                'parent_phone' => '081234567002',
                'parent_email' => 'siti.parent@email.com',
                'admission_date' => '2024-07-01',
                'status' => 'active'
            ],
            [
                'nis' => '202407003',
                'full_name' => 'Budi Santoso',
                'class_id' => 1,
                'birth_date' => '2012-02-10',
                'gender' => 'male',
                'address' => 'Jl. Gatot Subroto No. 67, Lubuk Pakam',
                'parent_phone' => '081234567003',
                'parent_email' => 'budi.parent@email.com',
                'admission_date' => '2024-07-01',
                'status' => 'active'
            ],

            // Kelas 7B
            [
                'nis' => '202407004',
                'full_name' => 'Dewi Lestari',
                'class_id' => 2,
                'birth_date' => '2012-04-25',
                'gender' => 'female',
                'address' => 'Jl. Thamrin No. 89, Lubuk Pakam',
                'parent_phone' => '081234567004',
                'parent_email' => 'dewi.parent@email.com',
                'admission_date' => '2024-07-01',
                'status' => 'active'
            ],
            [
                'nis' => '202407005',
                'full_name' => 'Joko Prasetyo',
                'class_id' => 2,
                'birth_date' => '2012-07-12',
                'gender' => 'male',
                'address' => 'Jl. Pahlawan No. 34, Lubuk Pakam',
                'parent_phone' => '081234567005',
                'parent_email' => 'joko.parent@email.com',
                'admission_date' => '2024-07-01',
                'status' => 'active'
            ],

            // Kelas 8A
            [
                'nis' => '202308001',
                'full_name' => 'Rina Wijaya',
                'class_id' => 4,
                'birth_date' => '2011-07-12',
                'gender' => 'female',
                'address' => 'Jl. Asia Afrika No. 101, Lubuk Pakam',
                'parent_phone' => '081234567006',
                'parent_email' => 'rina.parent@email.com',
                'admission_date' => '2023-07-01',
                'status' => 'active'
            ],
            [
                'nis' => '202308002',
                'full_name' => 'Agus Supriyadi',
                'class_id' => 4,
                'birth_date' => '2011-09-05',
                'gender' => 'male',
                'address' => 'Jl. Pahlawan No. 202, Lubuk Pakam',
                'parent_phone' => '081234567007',
                'parent_email' => 'agus.parent@email.com',
                'admission_date' => '2023-07-01',
                'status' => 'active'
            ],

            // Kelas 9A
            [
                'nis' => '202209001',
                'full_name' => 'Maya Sari',
                'class_id' => 7,
                'birth_date' => '2010-08-18',
                'gender' => 'female',
                'address' => 'Jl. Diponegoro No. 303, Lubuk Pakam',
                'parent_phone' => '081234567008',
                'parent_email' => 'maya.parent@email.com',
                'admission_date' => '2022-07-01',
                'status' => 'active'
            ],
            [
                'nis' => '202209002',
                'full_name' => 'Rizki Ramadhan',
                'class_id' => 7,
                'birth_date' => '2010-12-22',
                'gender' => 'male',
                'address' => 'Jl. Merpati No. 505, Lubuk Pakam',
                'parent_phone' => '081234567009',
                'parent_email' => 'rizki.parent@email.com',
                'admission_date' => '2022-07-01',
                'status' => 'active'
            ],

            // Siswa alumni (untuk testing filter)
            [
                'nis' => '202106001',
                'full_name' => 'Lia Amelia',
                'class_id' => 7,
                'birth_date' => '2009-10-14',
                'gender' => 'female',
                'address' => 'Jl. Rajawali No. 606, Lubuk Pakam',
                'parent_phone' => '081234567010',
                'parent_email' => 'lia.parent@email.com',
                'admission_date' => '2021-07-01',
                'status' => 'alumni'
            ],
        ];

        foreach ($students as $student) {
            Student::create($student);
        }

        $this->command->info('Data siswa berhasil ditambahkan: ' . count($students) . ' siswa');
    }
}
