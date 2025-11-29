<?php

namespace App\Http\Resources\Parent;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentFinanceDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'period' => $this['period'],
            'student_finance' => $this['student_finance'],
            'transactions' => $this['transactions']
        ];
    }
}
