<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketStatistic extends Model
{
    use HasFactory;

    public $table = 'market_statistics';

    public $fillable = [
        'id',
        'name',
        'coin_id',
        'top',
        'quantity',
        'pnl',
        'type',
        'is_new',
    ];
}
