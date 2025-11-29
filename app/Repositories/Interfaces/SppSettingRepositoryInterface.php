<?php

namespace App\Repositories\Interfaces;

use App\Models\SppSetting;
use Illuminate\Database\Eloquent\Collection;

interface SppSettingRepositoryInterface
{
    public function getAllSettings(array $filters = []): Collection;
    public function getSettingById(int $id): ?SppSetting;
    public function getSettingByGradeLevel(int $gradeLevel, ?int $academicYearId = null): ?SppSetting;
    public function getSettingsByAcademicYear(int $academicYearId): Collection;
    public function createSetting(array $data): SppSetting;
    public function updateSetting(int $id, array $data): bool;
    public function deleteSetting(int $id): bool;
    public function getActiveAcademicYearSettings(): Collection;
}
