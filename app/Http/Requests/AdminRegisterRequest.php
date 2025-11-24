<?php
// app/Http/Requests/AdminRegisterRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AdminRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        try {
            // Gunakan JWTAuth langsung untuk authorization
            $user = JWTAuth::parseToken()->authenticate();
            return $user && $user->isSuperAdmin();
        } catch (JWTException $e) {
            return false;
        }
    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException(
            'Hanya Super Admin yang dapat mendaftarkan admin baru.'
        );
    }

    public function rules(): array
    {
        return [
            'username'  => 'required|string|max:50|unique:users',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|string|min:6|confirmed',
            'full_name' => 'required|string|max:100',
            'role'      => 'required|in:super_admin,admin_attendance,admin_finance',
            'phone'     => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi',
            'username.unique' => 'Username sudah digunakan',
            'email.required' => 'Email wajib diisi',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 6 karakter',
            'full_name.required' => 'Nama lengkap wajib diisi',
            'role.required' => 'Role wajib dipilih',
            'role.in' => 'Role tidak valid',
        ];
    }
}
