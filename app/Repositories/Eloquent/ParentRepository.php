<?php
// app/Repositories/Eloquent/ParentRepository.php

namespace App\Repositories\Eloquent;

use App\Models\ParentModel;
use App\Models\Student;
use App\Repositories\Interfaces\ParentRepositoryInterface;

class ParentRepository implements ParentRepositoryInterface
{
    public function findByUsername(string $username)
    {
        return ParentModel::where('username', $username)->first();
    }

    public function findByEmail(string $email)
    {
        return ParentModel::where('email', $email)->first();
    }

    public function findByNis(string $nis)
    {
        // Cari parent melalui NIS siswa
        $student = Student::where('nis', $nis)->first();

        if (!$student) {
            return null;
        }

        // Ambil parent pertama yang terhubung dengan siswa
        return $student->parents()->first();
    }

    public function updateLastLogin(int $parentId)
    {
        return ParentModel::where('id', $parentId)->update([
            'last_login_at' => now()
        ]);
    }

    public function getParentWithStudents(int $parentId)
    {
        return ParentModel::with(['students.class'])->find($parentId);
    }

    public function createParent(array $data)
    {
        return ParentModel::create($data);
    }
}
