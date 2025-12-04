<?php

namespace App\Repositories\Eloquent;

use App\Models\Scholarship;
use App\Repositories\Interfaces\ScholarshipRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScholarshipRepository implements ScholarshipRepositoryInterface
{
    public function getAllWithStudent(array $filters = [])
    {
        $query = Scholarship::with([
            'student' => function($q) {
                $q->select('id', 'nis', 'full_name', 'class_id');
            },
            'student.class' => function($q) {
                $q->select('id', 'name', 'grade_level', 'academic_year_id');
            },
            'student.class.academicYear'
        ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['academic_year_id'])) {
            // Filter berdasarkan tahun akademik siswa
            $query->whereHas('student.class', function($q) use ($filters) {
                $q->where('academic_year_id', $filters['academic_year_id']);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getActiveScholarshipsByStudent(int $studentId)
    {
        return Scholarship::where('student_id', $studentId)
            ->where('status', 'active')
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now())
            ->get();
    }

    public function deactivateStudentScholarships(int $studentId)
    {
        return Scholarship::where('student_id', $studentId)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);
    }

    public function create(array $data)
    {
        return Scholarship::create($data);
    }

    public function find(int $id)
    {
        return Scholarship::find($id);
    }

    public function findWithStudent(int $id)
    {
        return Scholarship::with([
            'student' => function($q) {
                $q->select('id', 'nis', 'full_name', 'class_id');
            },
            'student.class' => function($q) {
                $q->select('id', 'name', 'grade_level', 'academic_year_id');
            },
            'student.class.academicYear'
        ])->find($id);
    }

    public function update(int $id, array $data)
    {
        $scholarship = Scholarship::find($id);
        if ($scholarship) {
            $scholarship->update($data);
            return $scholarship;
        }
        return false;
    }

    public function delete(int $id)
    {
        $scholarship = Scholarship::find($id);
        if ($scholarship) {
            return $scholarship->delete();
        }
        return false;
    }

    public function getByStudent(int $studentId)
    {
        return Scholarship::where('student_id', $studentId)
            ->with([
                'student' => function($q) {
                    $q->select('id', 'nis', 'full_name', 'class_id');
                },
                'student.class' => function($q) {
                    $q->select('id', 'name', 'grade_level', 'academic_year_id');
                },
                'student.class.academicYear' => function($q) {
                    $q->select('id', 'name', 'start_date', 'end_date');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getSummary()
    {
        return [
            'total' => Scholarship::count(),
            'active' => Scholarship::where('status', 'active')->count(),
            'expired' => Scholarship::where('status', 'expired')->count(),
            'full' => Scholarship::where('type', 'full')->count(),
            'partial' => Scholarship::where('type', 'partial')->count(),
        ];
    }

    public function getRecentScholarships(int $limit = 10)
    {
        return Scholarship::with([
            'student' => function($q) {
                $q->select('id', 'nis', 'full_name');
            },
            'student.class.academicYear'
        ])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
    }

    public function getScholarshipsByStudentAndAcademicYear(int $studentId, int $academicYearId)
    {
        return Scholarship::where('student_id', $studentId)
            ->whereHas('student.class', function($q) use ($academicYearId) {
                $q->where('academic_year_id', $academicYearId);
            })
            ->get();
    }

    public function checkDateOverlap(int $studentId, string $startDate, string $endDate, ?int $excludeId = null)
    {
        $query = Scholarship::where('student_id', $studentId)
            ->where('status', 'active')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
