<?php
// app/Repositories/Interfaces/ParentRepositoryInterface.php

namespace App\Repositories\Interfaces;

use App\Models\ParentModel;

interface ParentRepositoryInterface
{
    public function findByUsername(string $username);
    public function findByEmail(string $email);
    public function findByNis(string $nis);
    public function updateLastLogin(int $parentId);
    public function getParentWithStudents(int $parentId);
    public function createParent(array $data);
}
