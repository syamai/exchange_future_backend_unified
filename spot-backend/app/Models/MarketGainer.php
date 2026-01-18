<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketGainer extends Model
{
    use HasFactory;

    public $table = 'market_gainers';

    public $fillable = [
        'name',
        'top',
        'quantity',
        'lastest_price',
        'changed_percent',
    ];
}
