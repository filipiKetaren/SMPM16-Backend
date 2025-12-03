<?php
// app/Models/SppPaymentDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SppPaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'month',
        'year',
        'amount',
        'is_paid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_paid' => 'boolean',
    ];

    public function payment()
    {
        return $this->belongsTo(SppPayment::class, 'payment_id');
    }
}
