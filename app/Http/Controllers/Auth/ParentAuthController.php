<?php
// app/Http/Controllers/Auth/ParentAuthController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\ParentLoginResource;
use App\Http\Resources\Auth\ParentResource;
use App\Http\Resources\Auth\ParentTokenResource;
use App\Services\Auth\ParentAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ParentAuthController extends Controller
{
    protected $parentAuthService;

    public function __construct(ParentAuthService $parentAuthService)
    {
        $this->parentAuthService = $parentAuthService;
    }

    /**
     * Login orang tua menggunakan NIS siswa
     */
    public function login(Request $request)
    {
        Log::info('Parent login attempt:', $request->only('nis'));

        $result = $this->parentAuthService->login(
            $request->only(['nis', 'password']),
            $request->ip()
        );

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new ParentLoginResource($result['data'])
        ], $result['code']);
    }

    /**
     * Logout orang tua
     */
    public function logout(Request $request)
    {
        $result = $this->parentAuthService->logout();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json($result, $result['code']);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        $result = $this->parentAuthService->refreshToken();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new ParentTokenResource($result['data'])
        ], $result['code']);
    }

    /**
     * Get parent profile dengan data students
     */
    public function me(Request $request)
    {
        $result = $this->parentAuthService->getAuthenticatedParent($request);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new ParentResource($result['data'])
        ], $result['code']);
    }
}
