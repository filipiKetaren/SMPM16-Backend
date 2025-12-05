<?php

namespace App\Repositories\Interfaces;
use Illuminate\Pagination\LengthAwarePaginator;

interface ScholarshipRepositoryInterface
{
    public function getAllWithStudent(array $filters = []);
    public function getActiveScholarshipsByStudent(int $studentId);
    public function deactivateStudentScholarships(int $studentId);
    public function create(array $data);
    public function find(int $id);
    public function findWithStudent(int $id);
    public function update(int $id, array $data);
    public function delete(int $id);
    public function getByStudent(int $studentId);
    public function getSummary();
    public function getRecentScholarships(int $limit = 10);

    // Method baru untuk validasi akademik
    public function getScholarshipsByStudentAndAcademicYear(int $studentId, int $academicYearId);
    public function checkDateOverlap(int $studentId, string $startDate, string $endDate, ?int $excludeId = null);
    public function getAllScholarshipsPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator;
}
