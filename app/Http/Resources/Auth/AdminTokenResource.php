<?php
// app/Http/Resources/Auth/AdminTokenResource.php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = JWTAuth::setToken($this->resource)->authenticate();

        return [
            'access_token' => $this->resource,
            'token_type'   => 'bearer',
            'expires_in'   => JWTAuth::factory()->getTTL() * 60,
            'user_role'    => $user->role, // Ambil role dari user
        ];
    }
}
