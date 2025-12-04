<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SetTimezone
{
    public function handle(Request $request, Closure $next)
    {
        // Set timezone untuk aplikasi
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');

        // Set timezone untuk Carbon
        Carbon::setToStringFormat('Y-m-d H:i:s');

        // **TAMBAHKAN INI**: Set locale timezone untuk Carbon
        Carbon::setLocale('id');

        // **TAMBAHKAN INI**: Override serialization untuk Carbon
        Carbon::serializeUsing(function ($carbon) {
            return $carbon->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s.uP');
        });

        return $next($request);
    }
}
