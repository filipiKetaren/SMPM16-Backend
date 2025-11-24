<?php

namespace App\Http\Resources\Finance\Savings;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentSavingsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'student' => $this['student'],
            'savings' => $this['savings']
        ];
    }
}
