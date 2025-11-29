<?php
// app/Http/Controllers/Auth/AuthController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Auth\LoginResource;
use App\Http\Resources\Auth\RegisterResource;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Auth\AdminTokenResource;
use App\Services\Auth\AuthService;
use App\Services\Auth\UserService;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    protected $authService;
    protected $userService;

    public function __construct(AuthService $authService, UserService $userService)
    {
        $this->authService = $authService;
        $this->userService = $userService;
    }

    /**
     * Register ADMIN baru (hanya super_admin yang bisa)
     */
    public function register(Request $request)
    {
        try {
            // 1. Authorization Check - Manual
            $currentUser = JWTAuth::parseToken()->authenticate();

            if (!$currentUser || !$currentUser->isSuperAdmin()) {
                $result = [
                    'status' => 'error',
                    'message' => 'Hanya Super Admin yang dapat mendaftarkan admin baru.',
                    'code' => 403
                ];
                return response()->json($result, $result['code']);
            }

            // 2. Call Service (validasi akan ditangani di service)
            $result = $this->userService->createAdmin($request->all());

            if ($result['status'] === 'error') {
                return response()->json($result, $result['code']);
            }

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => new RegisterResource($result['data'])
            ], $result['code']);

        } catch (JWTException $e) {
            $result = [
                'status' => 'error',
                'message' => 'Token tidak valid',
                'code' => 401
            ];
            return response()->json($result, 401);
        } catch (\Exception $e) {
            $result = [
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem',
                'code' => 500
            ];
            return response()->json($result, 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $result = $this->authService->login(
            $request->only(['login', 'password']),
            $request->ip()
        );

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new LoginResource($result['data'])
        ], $result['code']);
    }

    /**
     * Logout user
     */
    public function logout()
    {
        $result = $this->authService->logout();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json($result, $result['code']);
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        $result = $this->authService->refreshToken();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new AdminTokenResource($result['data'])
        ], $result['code']);
    }

    /**
     * Get user profile
     */
    public function me()
    {
        $result = $this->authService->getAuthenticatedUser();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new UserResource($result['data'])
        ], $result['code']);
    }
}
