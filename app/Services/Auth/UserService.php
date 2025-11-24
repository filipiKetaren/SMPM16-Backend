<?php
// app/Services/Auth/UserService.php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

class UserService extends BaseService
{
    public function createAdmin(array $data)
    {
        try {
            // Validasi data
            $validator = Validator::make($data, [
                'username' => 'required|string|max:50|unique:users',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'full_name' => 'required|string|max:100',
                'role' => 'required|in:super_admin,admin_attendance,admin_finance',
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Create user
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'full_name' => $data['full_name'],
                'role' => $data['role'],
                'phone' => $data['phone'],
                'is_active' => true,
            ]);

            // Generate token
            $token = JWTAuth::fromUser($user);

            return $this->success([
                'user' => $user,
                'token' => $token
            ], 'Admin berhasil didaftarkan', 201);

        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal membuat admin', $e->getMessage(), 500);
        }
    }

    public function updateUserProfile(int $userId, array $data)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->error('User tidak ditemukan', null, 404);
            }

            $validator = Validator::make($data, [
                'full_name' => 'sometimes|string|max:100',
                'phone' => 'sometimes|nullable|string|max:20',
                'photo' => 'sometimes|nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->error('Validasi gagal', $validator->errors(), 422);
            }

            $user->update($data);

            return $this->success($user, 'Profile berhasil diupdate', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal update profile', $e->getMessage(), 500);
        }
    }

    public function deactivateUser(int $userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return $this->error('User tidak ditemukan', null, 404);
            }

            $user->update(['is_active' => false]);

            return $this->success(null, 'User berhasil dinonaktifkan', 200);

        } catch (\Exception $e) {
            return $this->error('Gagal menonaktifkan user', $e->getMessage(), 500);
        }
    }
}
