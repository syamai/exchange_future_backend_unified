<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingVolumeRankingTotal extends Model
{
    public $table = 'trading_volume_ranking_total';
    protected $fillable = [
        'id',
        'user_id',
        'email',
        'volume',
        'coin',
        'type',
        'data',
        'self_trading',
        'self_trading_btc_volume',
        'btc_volume',
        'created_at',
        'updated_at',
        'trading_volume'
    ];
}
