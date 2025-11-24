<?php
// app/Services/Auth/ParentJwtService.php

namespace App\Services\Auth;

use App\Models\ParentModel;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;

class ParentJwtService
{
    /**
     * Get ParentModel from token manually
     */
    public static function getParentFromToken($token = null)
    {
        try {
            if (!$token) {
                $token = JWTAuth::getToken();
            }

            $payload = JWTAuth::setToken($token)->getPayload();
            $parentId = $payload->get('sub');

            Log::info('ParentJwtService - Getting parent from token:', [
                'parent_id' => $parentId,
                'token_payload' => $payload->toArray()
            ]);

            // Ambil parent dengan relasi students
            $parent = ParentModel::with(['students.class'])->find($parentId);

            if (!$parent) {
                Log::warning('ParentJwtService - Parent not found:', ['parent_id' => $parentId]);
                return null;
            }

            Log::info('ParentJwtService - Parent found:', [
                'parent_id' => $parent->id,
                'parent_class' => get_class($parent)
            ]);

            return $parent;
        } catch (JWTException $e) {
            Log::error('JWT Exception in ParentJwtService::getParentFromToken:', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
            Log::error('General Exception in ParentJwtService::getParentFromToken:', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Validate parent token and check if parent is active
     */
    public static function validateParentToken($token = null)
    {
        $parent = self::getParentFromToken($token);
        return $parent && $parent->is_active;
    }

    /**
     * Invalidate token
     */
    public static function invalidateToken($token = null)
    {
        try {
            if (!$token) {
                $token = JWTAuth::getToken();
            }
            JWTAuth::setToken($token)->invalidate();
            return true;
        } catch (JWTException $e) {
            Log::error('JWT Exception in ParentJwtService::invalidateToken:', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Refresh token
     */
    public static function refreshToken($token = null)
    {
        try {
            if (!$token) {
                $token = JWTAuth::getToken();
            }
            return JWTAuth::setToken($token)->refresh();
        } catch (JWTException $e) {
            Log::error('JWT Exception in ParentJwtService::refreshToken:', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get token from request
     */
    public static function getTokenFromRequest($request)
    {
        return $request->bearerToken();
    }
}
