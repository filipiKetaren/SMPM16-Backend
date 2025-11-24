<?php
// app/Http/Middleware/ParentMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;

class ParentMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // DEBUG: Log informasi user
            Log::info('Parent Middleware Debug:', [
                'user_id' => $user ? $user->id : null,
                'user_class' => $user ? get_class($user) : null,
                'is_parent' => $user instanceof \App\Models\ParentModel,
                'is_user' => $user instanceof \App\Models\User,
                'token_payload' => JWTAuth::getPayload()->toArray()
            ]);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Cek jika user adalah instance dari ParentModel
            if (!$user instanceof \App\Models\ParentModel) {
                Log::warning('Access denied - User is not ParentModel:', [
                    'actual_class' => get_class($user),
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Hanya orang tua yang dapat mengakses.'
                ], 403);
            }

            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun orang tua tidak aktif'
                ], 403);
            }

            return $next($request);

        } catch (JWTException $e) {
            Log::error('JWT Exception in ParentMiddleware:', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Token tidak valid: ' . $e->getMessage()
            ], 401);
        } catch (\Exception $e) {
            Log::error('General Exception in ParentMiddleware:', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
