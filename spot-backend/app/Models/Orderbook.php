<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orderbook extends Model
{
    public $table = 'orderbooks';

    public $fillable = [
        'trade_type',
        'currency',
        'coin',
        'quantity',
        'count',
        'price',
        'ticker',
        'time'
    ];

    public $timestamps = false;

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];
}
