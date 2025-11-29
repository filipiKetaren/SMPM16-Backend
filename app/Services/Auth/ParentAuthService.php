<?php
// app/Services/Auth/ParentAuthService.php

namespace App\Services\Auth;

use App\Repositories\Interfaces\ParentRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ParentAuthService extends BaseService
{
    public function __construct(
        private ParentRepositoryInterface $parentRepository
    ) {}

    public function login(array $credentials, string $ipAddress)
    {
        try {
            // Validasi input
            $validator = Validator::make($credentials, [
                'nis' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors()->toArray(), 'Validasi gagal');
            }

            $nis = $credentials['nis'];

            // Cari parent melalui NIS siswa
            $parent = $this->parentRepository->findByNis($nis);

            if (!$parent) {
                return $this->error('NIS tidak ditemukan atau tidak terdaftar', null, 401);
            }

            if (!$parent->isActive()) {
                return $this->error('Akun orang tua tidak aktif', null, 401);
            }

            // Verifikasi password
            if (!Hash::check($credentials['password'], $parent->password)) {
                return $this->error('Password salah', null, 401);
            }

            // Generate token menggunakan JWTAuth langsung
            $token = JWTAuth::fromUser($parent);

            if (!$token) {
                return $this->error('Tidak dapat membuat token', null, 500);
            }

            // Update last login
            $this->parentRepository->updateLastLogin($parent->id);

            Log::info('Parent login successful:', [
                'parent_id' => $parent->id,
                'token_issued' => true
            ]);

            return $this->success([
                'parent' => $parent,
                'token' => $token
            ], 'Login berhasil', 200);

        } catch (JWTException $e) {
            Log::error('JWT Exception in ParentAuthService login:', ['error' => $e->getMessage()]);
            return $this->error('Tidak dapat membuat token', null, 500);
        } catch (\Exception $e) {
            Log::error('General Exception in ParentAuthService login:', ['error' => $e->getMessage()]);
            return $this->serverError('Terjadi kesalahan sistem', $e);
        }
    }

    public function logout()
    {
        try {
            // Gunakan ParentJwtService untuk logout
            $success = ParentJwtService::invalidateToken();

            if ($success) {
                return $this->success(null, 'Logout berhasil', 200);
            } else {
                return $this->error('Gagal logout, token tidak valid', null, 500);
            }
        } catch (\Exception $e) {
            Log::error('Exception in ParentAuthService logout:', ['error' => $e->getMessage()]);
            return $this->serverError('Gagal logout', $e);
        }
    }

    public function refreshToken()
    {
        try {
            // Gunakan ParentJwtService untuk refresh token
            $newToken = ParentJwtService::refreshToken();

            if ($newToken) {
                return $this->success($newToken, 'Token berhasil direfresh', 200);
            } else {
                return $this->error('Gagal refresh token', null, 401);
            }
        } catch (\Exception $e) {
            Log::error('Exception in ParentAuthService refreshToken:', ['error' => $e->getMessage()]);
            return $this->serverError('Gagal refresh token', $e);
        }
    }

    public function getAuthenticatedParent($request)
    {
        try {
            // Sekarang parent sudah diattach oleh middleware
            $parent = $request->parent;

            if (!$parent) {
                Log::warning('ParentAuthService - No parent attached to request');
                return $this->notFoundError('Parent tidak ditemukan');
            }

            Log::info('ParentAuthService - Parent retrieved from request:', [
                'parent_id' => $parent->id,
                'parent_class' => get_class($parent)
            ]);

            // Pastikan relationship sudah diload
            if (!$parent->relationLoaded('students')) {
                $parent->load(['students.class']);
            }

            return $this->success($parent, 'Data orang tua berhasil diambil', 200);

        } catch (\Exception $e) {
            Log::error('Exception in ParentAuthService getAuthenticatedParent:', ['error' => $e->getMessage()]);
            return $this->serverError('Gagal mengambil data orang tua', $e);
        }
    }
}
