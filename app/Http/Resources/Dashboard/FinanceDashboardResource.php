<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class FinanceDashboardResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'period' => $this['period'],
            'stats' => $this['stats'],
            'charts' => $this['charts'],
            'recent_activities' => $this['recent_activities']
        ];
    }
}
