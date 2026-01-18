<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingVolumeRanking extends Model
{
    protected $table = 'trading_volume_ranking';
    protected $fillable = ['user_id', 'email', 'volume', 'coin', 'market', 'type', 'self_trading', 'self_trading_btc_volume', 'btc_volume','trading_volume'];
}
