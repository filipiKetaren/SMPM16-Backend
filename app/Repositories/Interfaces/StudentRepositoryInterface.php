<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;

interface StudentRepositoryInterface
{
    public function getActiveStudentsWithClassAndPayments(array $filters = []): Collection;
    public function findStudentWithClassAndPayments(int $id);
    public function findStudentWithPaymentHistory(int $id);
    public function findStudentWithClass(int $studentId);
    public function getStudentById(int $id);
}
