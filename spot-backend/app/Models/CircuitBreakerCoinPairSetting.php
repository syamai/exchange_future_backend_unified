<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CircuitBreakerCoinPairSetting extends Model
{
    protected $table = 'circuit_breaker_coin_pair_settings';
    public $fillable = [
        'currency',
        'coin',
        'range_listen_time',
        'circuit_breaker_percent',
        'block_time',
        'status',
        'locked_at',
        'unlocked_at',
        'last_order_transaction_id',
        'block_trading',
        'last_price',
    ];
}
