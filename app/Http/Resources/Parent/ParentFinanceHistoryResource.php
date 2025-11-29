<?php

namespace App\Http\Resources\Parent;

use Illuminate\Http\Resources\Json\JsonResource;

class ParentFinanceHistoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'period' => $this['period'],
            'students' => $this['students'],
            'transactions' => $this['transactions'],
            'summary' => $this['summary']
        ];
    }
}
