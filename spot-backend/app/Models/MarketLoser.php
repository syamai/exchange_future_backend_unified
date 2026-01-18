<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketLoser extends Model
{
    use HasFactory;

    public $table = 'market_losers';

    public $fillable = [
        'name',
        'top',
        'quantity',
        'lastest_price',
        'changed_percent',
    ];
}
