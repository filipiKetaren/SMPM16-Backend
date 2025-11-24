<?php
// app/Services/Auth/AuthService.php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class AuthService extends BaseService
{
    public function login(array $credentials, string $ipAddress)
    {
        try {
            // Validasi input
            $validator = Validator::make($credentials, [
                'login' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->error('Validasi gagal', $validator->errors(), 422);
            }

            $login = $credentials['login'];
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $authCredentials = [
                $field => $login,
                'password' => $credentials['password']
            ];

            // Attempt login
            if (!$token = JWTAuth::attempt($authCredentials)) {
                return $this->error('Kredensial tidak valid', null, 401);
            }

            // Get user
            $user = User::where($field, $login)->first();

            if (!$user) {
                return $this->error('User tidak ditemukan', null, 404);
            }

            if (!$user->is_active) {
                return $this->error('Akun tidak aktif', null, 401);
            }

            // Update last login
            $user->update([
                'last_login_at' => Carbon::now(),
                'last_login_ip' => $ipAddress,
            ]);

            return $this->success([
                'user' => $user,
                'token' => $token
            ], 'Login berhasil', 200);

        } catch (JWTException $e) {
            return $this->error('Tidak dapat membuat token', $e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->error('Terjadi kesalahan sistem', $e->getMessage(), 500);
        }
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->success(null, 'User logged out successfully', 200);
        } catch (JWTException $e) {
            return $this->error('Failed to logout, token invalid', $e->getMessage(), 500);
        }
    }

    public function refreshToken()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return $this->success($newToken, 'Token refreshed', 200);
        } catch (JWTException $e) {
            return $this->error('Failed to refresh token', $e->getMessage(), 401);
        }
    }

    public function getAuthenticatedUser()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return $this->success($user, 'User profile fetched', 200);
        } catch (JWTException $e) {
            return $this->error('Token tidak valid atau expired', $e->getMessage(), 401);
        }
    }
}
