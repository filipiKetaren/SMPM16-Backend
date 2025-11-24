<?php
// app/Http/Resources/Auth/ParentTokenResource.php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Tymon\JWTAuth\Facades\JWTAuth;

class ParentTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Jika resource adalah string (token), kembalikan format token
        if (is_string($this->resource)) {
            return [
                'access_token' => $this->resource,
                'token_type'   => 'bearer',
                'expires_in'   => JWTAuth::factory()->getTTL() * 60,
                'user_role'    => 'parent',
            ];
        }

        // Fallback untuk format lama (jika masih ada)
        return [
            'access_token' => $this->resource,
            'token_type'   => 'bearer',
            'expires_in'   => JWTAuth::factory()->getTTL() * 60,
            'user_role'    => 'parent',
        ];
    }
}
