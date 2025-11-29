<?php

namespace App\Repositories\Eloquent;

use App\Models\AcademicYear;
use App\Repositories\Interfaces\AcademicYearRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AcademicYearRepository implements AcademicYearRepositoryInterface
{
    public function getAll(): Collection
    {
        return AcademicYear::orderBy('start_date', 'desc')->get();
    }

    public function findById(int $id): ?AcademicYear
    {
        return AcademicYear::find($id);
    }

    public function getActiveAcademicYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    public function create(array $data): AcademicYear
    {
        return AcademicYear::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $academicYear = AcademicYear::find($id);
        if (!$academicYear) {
            return false;
        }

        return $academicYear->update($data);
    }

    public function delete(int $id): bool
    {
        $academicYear = AcademicYear::find($id);
        if (!$academicYear) {
            return false;
        }

        return $academicYear->delete();
    }

    public function deactivateAll(): bool
    {
        return AcademicYear::query()->update(['is_active' => false]);
    }
}
