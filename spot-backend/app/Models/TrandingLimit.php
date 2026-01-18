<?php

namespace App\Models;

use AWS\CRT\HTTP\Request;
use Illuminate\Database\Eloquent\Model;

class TrandingLimit extends Model
{
    protected $table = 'trading_limits';

    protected $fillable = [
        'coin' , 'currency', 'sell_limit', 'buy_limit', 'days'
    ];

    public static function getList(): \Illuminate\Database\Eloquent\Collection
    {
        return TrandingLimit::all();
    }

    public static function scopeCoin($query, $coin)
    {
        return $query->where('coin', $coin);
    }

    public static function scopeCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public static function scopeParams($query, $params) {
        foreach ($params as $key => $val) {
            $query->where($key, $val);
        }
        return $query;
    }
}
