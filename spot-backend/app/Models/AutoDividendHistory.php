<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDividendHistory extends Model
{
    //
    protected $table = "dividend_auto_history";

    protected $fillable = [
        'user_id',
        'email',
        'currency',
        'market',
        'transaction_id',
        'bonus_currency',
        'bonus_amount',
        'bonus_wallet',
        'bonus_date',
        'type',
        'status',
        'dividend_settings'
    ];
}
