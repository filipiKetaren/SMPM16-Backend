<?php
// app/Models/ParentModel.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;

class ParentModel extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'parents';

    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'phone',
        'photo',
        'role',
        'is_active',
        'fcm_token',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => 'parent',
    ];

    // Relationship dengan students
    public function students()
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
                    ->withPivot('relationship')
                    ->withTimestamps();
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    // Implementasi JWT - TAMBAHKAN PRV CLAIM
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'guard' => 'parent',
            'prv' => 'parent' // Ini penting untuk JWT subject locking
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
