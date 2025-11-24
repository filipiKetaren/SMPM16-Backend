<?php
// database/seeders/SppSettingSeeder.php

namespace Database\Seeders;

use App\Models\SppSetting;
use Illuminate\Database\Seeder;

class SppSettingSeeder extends Seeder
{
    public function run(): void
    {
        SppSetting::create([
            'academic_year_id' => 1,
            'grade_level' => 7,
            'monthly_amount' => 150000,
            'due_date' => 10,
            'late_fee_enabled' => true,
            'late_fee_type' => 'percentage',
            'late_fee_amount' => 2,
            'late_fee_start_day' => 11,
        ]);

        SppSetting::create([
            'academic_year_id' => 1,
            'grade_level' => 8,
            'monthly_amount' => 175000,
            'due_date' => 10,
            'late_fee_enabled' => true,
            'late_fee_type' => 'percentage',
            'late_fee_amount' => 2,
            'late_fee_start_day' => 11,
        ]);

        SppSetting::create([
            'academic_year_id' => 1,
            'grade_level' => 9,
            'monthly_amount' => 200000,
            'due_date' => 10,
            'late_fee_enabled' => true,
            'late_fee_type' => 'percentage',
            'late_fee_amount' => 2,
            'late_fee_start_day' => 11,
        ]);

        $this->command->info('Setting SPP berhasil ditambahkan untuk semua tingkat');
    }
}
