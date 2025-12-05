<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasCustomTimestamps;

class SppPaymentResource extends JsonResource
{
    use HasCustomTimestamps;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'student_id' => $this->student_id,
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'late_fee' => (float) $this->late_fee,
            'total_amount' => (float) $this->total_amount,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'student' => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'full_name' => $this->student->full_name,
                'class_id' => $this->student->class_id,
                'photo' => $this->student->photo,
                'birth_date' => $this->student->birth_date,
                'gender' => $this->student->gender,
                'address' => $this->student->address,
                'parent_phone' => $this->student->parent_phone,
                'parent_email' => $this->student->parent_email,
                'status' => $this->student->status,
                'admission_date' => $this->student->admission_date,
            ],
            'creator' => [
                'id' => $this->creator->id,
                'username' => $this->creator->username,
                'full_name' => $this->creator->full_name,
                'email' => $this->creator->email,
                'role' => $this->creator->role,
                'phone' => $this->creator->phone,
                'photo' => $this->creator->photo,
                'is_active' => $this->creator->is_active,
                'last_login_at' => $this->creator->last_login_at,
                'last_login_ip' => $this->creator->last_login_ip,
            ],
            'payment_details' => $this->paymentDetails->map(function($detail) {
                return [
                    'id' => $detail->id,
                    'payment_id' => $detail->payment_id,
                    'month' => $detail->month,
                    'year' => $detail->year,
                    'amount' => (float) $detail->amount,
                    'created_at' => $this->formatTimestamp($detail->created_at),
                    'updated_at' => $this->formatTimestamp($detail->updated_at),
                ];
            })
        ];
    }
}
