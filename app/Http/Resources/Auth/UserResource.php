<?php
// app/Http/Resources/Auth/UserResource.php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasCustomTimestamps;

class UserResource extends JsonResource
{
    use HasCustomTimestamps;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'role' => $this->role,
            'phone' => $this->phone,
            'photo' => $this->photo,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->formatTimestamp($this->created_at),
            'updated_at' => $this->formatTimestamp($this->updated_at),
        ];
    }
}
