<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    public $table = 'settlements';

    public $fillable = [
        'symbol',
        'settled_price',
        'option_strike_price',
        'option_underlying_price',
        'tax_base',
        'tax_rate',
        'settlement_type',
        'timestamps'
    ];
}
