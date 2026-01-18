<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketFeeSetting extends Model
{
    public $table = 'market_fee_setting';

    public $fillable = [
        'id',
        'currency',
        'coin',
        'fee_taker',
        'fee_maker'
    ];
}
