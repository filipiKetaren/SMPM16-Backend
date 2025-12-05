<?php

namespace App\Repositories\Eloquent;

use App\Models\AcademicYear;
use App\Repositories\Interfaces\AcademicYearRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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

    // Hitung jumlah tahun akademik aktif
    public function countActiveAcademicYears(int $excludeId = null): int
    {
        $query = AcademicYear::where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->count();
    }

    // Dapatkan tahun akademik aktif (kecuali exclude)
    public function getActiveAcademicYearsExcept(int $excludeId = null): Collection
    {
        $query = AcademicYear::where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function getAllPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator
    {
        $query = AcademicYear::query();

        // Apply filters
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['year'])) {
            $query->where('start_date', 'like', $filters['year'] . '%');
        }

        return $query->orderBy('start_date', 'desc')->paginate($perPage);
    }
}
