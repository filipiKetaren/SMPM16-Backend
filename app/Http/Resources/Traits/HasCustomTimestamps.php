<?php

namespace App\Http\Resources\Traits;

use Carbon\Carbon;

trait HasCustomTimestamps
{
    /**
     * Format timestamp dengan timezone Asia/Jakarta
     */
    protected function formatTimestamp($timestamp)
    {
        if (!$timestamp) {
            return null;
        }

        return Carbon::parse($timestamp)
            ->timezone('Asia/Jakarta')
            ->format('Y-m-d\TH:i:s.uP'); // Format: 2025-12-04T21:25:55.000000+07:00
    }

    /**
     * Format date only dengan timezone Asia/Jakarta
     */
    protected function formatDate($date)
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)
            ->timezone('Asia/Jakarta')
            ->format('Y-m-d');
    }
}
