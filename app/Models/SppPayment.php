<?php
// app/Models/SppPayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SppPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number', 'student_id', 'payment_date', 'subtotal',
        'discount', 'late_fee', 'total_amount', 'payment_method',
        'notes', 'created_by'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentDetails()
    {
        return $this->hasMany(SppPaymentDetail::class, 'payment_id');
    }
}
