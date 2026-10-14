<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'price_monthly', 'price_yearly', 'hours_quota', 'request_quota',
        'addon_hour_price', 'addon_request_price', 'uptime_monitor_limit', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
