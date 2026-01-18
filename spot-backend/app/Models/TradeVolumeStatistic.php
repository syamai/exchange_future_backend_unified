<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeVolumeStatistic extends Model
{
    public $table = 'trade_volume_statistics';
    protected $fillable = [
        'user_id',
        'email',
        'coin',
        'market',
        'type',
        'volume_excess'
    ];
}
