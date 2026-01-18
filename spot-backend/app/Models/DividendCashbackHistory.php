<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendCashbackHistory extends Model
{
    public $table = 'dividend_cashback_histories';

    public $fillable = [
        'user_id',
        'email',
        'cashback_id',
        'amount',
        'status'
    ];
}
