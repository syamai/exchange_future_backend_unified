<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CircuitBreakerSetting extends Model
{
    protected $table = 'circuit_breaker_settings';

    public $fillable = [
        'range_listen_time',
        'circuit_breaker_percent',
        'block_time',
        'status',
    ];
}
