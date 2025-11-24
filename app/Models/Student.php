<?php
// app/Models/Student.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'nis', 'full_name', 'class_id', 'photo', 'birth_date',
        'gender', 'address', 'parent_phone', 'parent_email',
        'status', 'admission_date'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'admission_date' => 'date',
    ];

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function sppPayments()
    {
        return $this->hasMany(SppPayment::class);
    }

    public function savingsTransactions()
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    // Relationship dengan parents
    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'parent_student', 'student_id', 'parent_id')
                    ->withPivot('relationship')
                    ->withTimestamps();
    }

    // Method untuk menghitung saldo tabungan saat ini
    public function getCurrentSavingsBalance()
    {
        $lastTransaction = $this->savingsTransactions()
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance_after : 0;
    }
}
