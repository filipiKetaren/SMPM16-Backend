<?php
// app/Models/SppSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'grade_level',
        'monthly_amount',
        'due_date',
        'late_fee_enabled',
        'late_fee_type',
        'late_fee_amount',
        'late_fee_start_day',
    ];

    protected $casts = [
        'monthly_amount' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'late_fee_enabled' => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
