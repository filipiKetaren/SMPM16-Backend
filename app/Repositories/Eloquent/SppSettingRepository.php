<?php

namespace App\Repositories\Eloquent;

use App\Models\SppSetting;
use App\Models\Student;
use App\Repositories\Interfaces\SppSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SppSettingRepository implements SppSettingRepositoryInterface
{
    public function getAllSettings(array $filters = []): Collection
    {
        $query = SppSetting::with('academicYear');

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['grade_level'])) {
            $query->where('grade_level', $filters['grade_level']);
        }

        return $query->orderBy('academic_year_id', 'desc')
                    ->orderBy('grade_level')
                    ->get();
    }

    public function getSettingById(int $id): ?SppSetting
    {
        return SppSetting::with('academicYear')->find($id);
    }

    public function getSettingByGradeLevel(int $gradeLevel, ?int $academicYearId = null): ?SppSetting
    {
        $query = SppSetting::where('grade_level', $gradeLevel);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->first();
    }

    public function getSettingsByAcademicYear(int $academicYearId): Collection
    {
        return SppSetting::with('academicYear')
            ->where('academic_year_id', $academicYearId)
            ->orderBy('grade_level')
            ->get();
    }

    public function createSetting(array $data): SppSetting
    {
        return SppSetting::create($data);
    }

    public function updateSetting(int $id, array $data): bool
    {
        $setting = SppSetting::find($id);
        if (!$setting) {
            return false;
        }

        return $setting->update($data);
    }

    public function deleteSetting(int $id): bool
    {
        $setting = SppSetting::find($id);
        if (!$setting) {
            return false;
        }

        return $setting->delete();
    }

    public function getActiveAcademicYearSettings(): Collection
    {
        return SppSetting::with(['academicYear'])
            ->whereHas('academicYear', function($query) {
                $query->where('is_active', true);
            })
            ->orderBy('grade_level')
            ->get();
    }
}
