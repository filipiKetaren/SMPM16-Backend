<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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
        return Student::with([
            'class' => function($q) {
                $q->select('id', 'name', 'grade_level', 'academic_year_id');
            },
            'class.academicYear' => function($q) {
                $q->select('id', 'name');
            }
        ])->find($id);
    }

        public function findStudentWithClass(int $studentId)
    {
        return Student::with('class')->find($studentId);
    }

    /**
     * Get active students with class (paginated)
     */
    public function getActiveStudentsWithClassPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator
    {
        $query = Student::with(['class', 'savingsTransactions' => function($q) {
            $q->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');
        }])
        ->where('status', 'active');

        // Apply filters
        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['nis'])) {
            $query->where('nis', 'like', '%' . $filters['nis'] . '%');
        }

        if (isset($filters['full_name'])) {
            $query->where('full_name', 'like', '%' . $filters['full_name'] . '%');
        }

        if (isset($filters['grade_level'])) {
            $query->whereHas('class', function($q) use ($filters) {
                $q->where('grade_level', $filters['grade_level']);
            });
        }

        return $query->orderBy('nis')->paginate($perPage);
    }

    /**
     * Get students with SPP bills (paginated)
     */
    public function getStudentsWithBillsPaginated(array $filters = [], int $perPage = 5): LengthAwarePaginator
    {
        $query = Student::with(['class', 'sppPayments.paymentDetails'])
            ->where('status', 'active');

        // Apply filters
        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['nis'])) {
            $query->where('nis', 'like', '%' . $filters['nis'] . '%');
        }

        if (isset($filters['full_name'])) {
            $query->where('full_name', 'like', '%' . $filters['full_name'] . '%');
        }

        if (isset($filters['grade_level'])) {
            $query->whereHas('class', function($q) use ($filters) {
                $q->where('grade_level', $filters['grade_level']);
            });
        }

        return $query->orderBy('nis')->paginate($perPage);
    }
}
