<?php
// app/Http/Resources/Auth/ParentResource.php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasCustomTimestamps;

class ParentResource extends JsonResource
{
    use HasCustomTimestamps;
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'photo' => $this->photo,
            'is_active' => $this->is_active,
            'last_login_at' => $this->formatTimestamp($this->last_login_at),
            'created_at' => $this->formatTimestamp($this->created_at),
            'updated_at' => $this->formatTimestamp($this->updated_at),
            'students' => $this->students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'class' => $student->class->name ?? 'Tidak ada kelas',
                    'gender' => $student->gender,
                    'status' => $student->status,
                ];
            }),
        ];
    }
}
