<?php
// app/Http/Resources/Auth/ParentLoginResource.php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParentLoginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'parent' => new ParentResource($this['parent']),
            'token' => new ParentTokenResource($this['token']),
        ];
    }
}
