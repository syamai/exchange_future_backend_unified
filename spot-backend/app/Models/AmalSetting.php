<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmalSetting extends Model
{
    use HasFactory;

    public $table = 'amal_settings';
    public $fillable = [
        'amount',
        'total',
        'usd_price',
        'eth_price',
        'btc_price',
        'usdt_price',

        'usd_price_presenter',
        'eth_price_presenter',
        'btc_price_presenter',

        'usd_price_presentee',
        'eth_price_presentee',
        'btc_price_presentee',

        'usd_sold_amount',
        'eth_sold_amount',
        'btc_sold_amount',
        'usdt_sold_amount',

        'usd_sold_money',
        'eth_sold_money',
        'btc_sold_money',
        'usdt_sold_money',

        'amal_bonus_1',
        'percent_bonus_1',
        'amal_bonus_2',
        'percent_bonus_2',

        'referrer_commision_percent',
        'referred_bonus_percent',
    ];

    public function scopeFilter($query, $input)
    {
        foreach ($this->fillable as $value) {
            if (isset($input[$value])) {
                $query->where($value, $input[$value]);
            }
        }
        return $query;
    }
}
