<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinMarketCapTicker extends Model
{
    // protected $connection = 'bot';
    protected $fillable = [
        'name', 'symbol', 'rank', 'price_usd', 'price_btc', '24h_volume_usd',
        'market_cap_usd', 'available_supply', 'total_supply', 'max_supply',
        'percent_change_1h', 'percent_change_24h', 'percent_change_7d',
        'last_updated', 'price_usd', '24h_volume_usd', 'market_cap_usd',
        'price_currency', '24h_volume_currency', 'market_cap_currency'
    ];
}
