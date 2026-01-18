<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDividendSetting extends Model
{
    public $table = 'dividend_auto_settings';
    protected $fillable = [
        'enable',
        'market',
        'coin',
        'time_from',
        'time_to',
        'payout_amount',
        'payout_coin',
        'lot',
        'payfor',
        'setting_for',
        'max_bonus',
        'is_show'
    ];
}
