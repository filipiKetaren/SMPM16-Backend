<?php
// database/seeders/ClassSeeder.php

namespace Database\Seeders;

use App\Models\ClassModel;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            // Kelas 7
            ['name' => '7A', 'grade_level' => 7, 'academic_year_id' => 1, 'homeroom_teacher' => 'Bu Siti'],
            ['name' => '7B', 'grade_level' => 7, 'academic_year_id' => 1, 'homeroom_teacher' => 'Pak Budi'],
            ['name' => '7C', 'grade_level' => 7, 'academic_year_id' => 1, 'homeroom_teacher' => 'Bu Rina'],

            // Kelas 8
            ['name' => '8A', 'grade_level' => 8, 'academic_year_id' => 1, 'homeroom_teacher' => 'Pak Joko'],
            ['name' => '8B', 'grade_level' => 8, 'academic_year_id' => 1, 'homeroom_teacher' => 'Bu Dewi'],
            ['name' => '8C', 'grade_level' => 8, 'academic_year_id' => 1, 'homeroom_teacher' => 'Pak Agus'],

            // Kelas 9
            ['name' => '9A', 'grade_level' => 9, 'academic_year_id' => 1, 'homeroom_teacher' => 'Bu Maya'],
            ['name' => '9B', 'grade_level' => 9, 'academic_year_id' => 1, 'homeroom_teacher' => 'Pak Rudi'],
            ['name' => '9C', 'grade_level' => 9, 'academic_year_id' => 1, 'homeroom_teacher' => 'Bu Sari'],
        ];

        foreach ($classes as $class) {
            ClassModel::create($class);
        }
    }
}
