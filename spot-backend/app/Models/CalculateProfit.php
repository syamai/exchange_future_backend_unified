<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculateProfit extends Model
{
    public $table = 'calculate_profit_daily';

    public $fillable = [
        'date',
        'coin',
        'receive_fee',
        'referral_fee',
        'net_fee',
        'symbol',
        'client_referral_fee'
    ];
}
