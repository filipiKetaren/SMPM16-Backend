<?php
// app/Http/Middleware/ParentAuthMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\ParentJwtService;

class ParentAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        Log::info('ParentAuthMiddleware - Processing request:', [
            'has_token' => !empty($token),
            'url' => $request->url()
        ]);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token tidak provided'
            ], 401);
        }

        // Validasi token dan ambil parent menggunakan custom service
        $parent = ParentJwtService::getParentFromToken($token);

        if (!$parent) {
            Log::warning('ParentAuthMiddleware - Parent not found from token');
            return response()->json([
                'status' => 'error',
                'message' => 'Token tidak valid untuk orang tua'
            ], 401);
        }

        if (!$parent->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun orang tua tidak aktif'
            ], 403);
        }

        Log::info('ParentAuthMiddleware - Parent authenticated successfully:', [
            'parent_id' => $parent->id,
            'parent_name' => $parent->full_name
        ]);

        // Attach parent ke request agar bisa digunakan di controller
        $request->merge(['parent' => $parent]);
        $request->setUserResolver(function () use ($parent) {
            return $parent;
        });

        return $next($request);
    }
}
