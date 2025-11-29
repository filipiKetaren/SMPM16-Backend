<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SppSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->academicYear->name,
            'grade_level' => $this->grade_level,
            'monthly_amount' => (float) $this->monthly_amount,
            'due_date' => $this->due_date,
            'late_fee_enabled' => (bool) $this->late_fee_enabled,
            'late_fee_type' => $this->late_fee_type,
            'late_fee_amount' => $this->late_fee_amount ? (float) $this->late_fee_amount : null,
            'late_fee_start_day' => $this->late_fee_start_day,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
