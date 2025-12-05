<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface
{
    public function getActiveStudentsWithClassAndPayments(array $filters = []): Collection;
    public function findStudentWithClassAndPayments(int $id);
    public function findStudentWithPaymentHistory(int $id);
    public function findStudentWithClass(int $studentId);
    public function getStudentById(int $id);
    public function getActiveStudentsWithClassPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator;
    public function getStudentsWithBillsPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator;
}
