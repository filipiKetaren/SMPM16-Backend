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
                return $this->validationError($validator->errors()->toArray(), 'Validasi gagal');
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
                return $this->notFoundError('User tidak ditemukan');
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
            return $this->error('Tidak dapat membuat token', null, 500);
        } catch (\Exception $e) {
            return $this->serverError('Terjadi kesalahan sistem', $e);
        }
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->success(null, 'Logout berhasil', 200);
        } catch (JWTException $e) {
            return $this->error('Gagal logout, token tidak valid', null, 500);
        } catch (\Exception $e) {
            return $this->serverError('Gagal logout', $e);
        }
    }

    public function refreshToken()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return $this->success($newToken, 'Token berhasil direfresh', 200);
        } catch (JWTException $e) {
            return $this->error('Gagal refresh token', null, 401);
        } catch (\Exception $e) {
            return $this->serverError('Gagal refresh token', $e);
        }
    }

    public function getAuthenticatedUser()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->notFoundError('User tidak ditemukan');
            }

            return $this->success($user, 'Data user berhasil diambil', 200);
        } catch (JWTException $e) {
            return $this->error('Token tidak valid atau expired', null, 401);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data user', $e);
        }
    }
}
