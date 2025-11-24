<?php

namespace App\Repositories\Eloquent;

use App\Models\SppSetting;
use App\Repositories\Interfaces\SppSettingRepositoryInterface;

class SppSettingRepository implements SppSettingRepositoryInterface
{
    public function getSettingByGradeLevel(int $gradeLevel)
    {
        return SppSetting::where('grade_level', $gradeLevel)
            ->whereHas('academicYear', function($query) {
                $query->where('is_active', true);
            })
            ->first();
    }
}
