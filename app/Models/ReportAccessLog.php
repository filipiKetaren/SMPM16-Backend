<?php
// app/Models/ReportAccessLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_type',
        'period_type',
        'year',
        'month',
        'accessed_at'
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
