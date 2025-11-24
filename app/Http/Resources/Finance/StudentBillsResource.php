<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentBillsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'student' => [
                'id' => $this['student']['id'],
                'nis' => $this['student']['nis'],
                'full_name' => $this['student']['full_name'],
                'class' => $this['student']['class'],
                'grade_level' => $this['student']['grade_level'],
            ],
            'bills' => [
                'year' => $this['bills']['year'],
                'monthly_amount' => $this['bills']['monthly_amount'],
                'unpaid_months' => $this['bills']['unpaid_months'],
                'paid_months' => $this['bills']['paid_months'],
                'total_unpaid' => $this['bills']['total_unpaid'],
                'unpaid_details' => $this['bills']['unpaid_details'],
                'paid_details' => $this['bills']['paid_details']
            ]
        ];
    }
}
