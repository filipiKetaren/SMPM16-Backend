<?php

namespace App\Repositories\Interfaces;

interface SppSettingRepositoryInterface
{
    public function getSettingByGradeLevel(int $gradeLevel);
}
