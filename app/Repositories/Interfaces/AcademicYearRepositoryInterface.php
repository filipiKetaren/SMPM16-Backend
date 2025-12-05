<?php

namespace App\Repositories\Interfaces;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AcademicYearRepositoryInterface
{
    public function getAll(): Collection;
    public function findById(int $id): ?AcademicYear;
    public function getActiveAcademicYear(): ?AcademicYear;
    public function create(array $data): AcademicYear;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function deactivateAll(): bool;
    public function countActiveAcademicYears(int $excludeId = null): int;
    public function getActiveAcademicYearsExcept(int $excludeId = null): Collection;
    public function getAllPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator;
}
