<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class StudentRepository implements StudentRepositoryInterface
{
    public function getActiveStudentsWithClassAndPayments(array $filters = []): Collection
    {
        return Student::with(['class', 'sppPayments.paymentDetails'])
            ->when(isset($filters['class_id']), function($query) use ($filters) {
                return $query->where('class_id', $filters['class_id']);
            })
            ->when(isset($filters['status']), function($query) use ($filters) {
                return $query->where('status', $filters['status']);
            })
            ->where('status', 'active')
            ->get();
    }

    public function findStudentWithClassAndPayments(int $id)
    {
        return Student::with(['class', 'sppPayments.paymentDetails'])->find($id);
    }

   public function findStudentWithPaymentHistory(int $id)
    {
        return Student::with([
            'sppPayments' => function($query) {
                $query->orderBy('payment_date', 'desc');
            },
            'sppPayments.paymentDetails',
            'sppPayments.creator'
        ])->find($id);
    }

    public function getStudentById(int $id)
    {
        return Student::find($id);
    }

        public function findStudentWithClass(int $studentId)
    {
        return Student::with('class')->find($studentId);
    }
}
