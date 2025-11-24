<?php
// app/Http/Middleware/FinanceAdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class FinanceAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Pastikan yang akses adalah User (bukan Parent)
            if (!$user instanceof \App\Models\User) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Hanya Admin yang dapat mengakses.'
                ], 403);
            }

            // Cek role dari user
            if (!$user->isFinanceAdmin() && !$user->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Hanya Admin Keuangan yang dapat mengakses.'
                ], 403);
            }

            return $next($request);

        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token tidak valid'
            ], 401);
        }
    }
}
