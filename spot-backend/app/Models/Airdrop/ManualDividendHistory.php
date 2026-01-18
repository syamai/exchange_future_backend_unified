<?php

namespace App\Models\Airdrop;

use Illuminate\Database\Eloquent\Model;

class ManualDividendHistory extends Model
{
    //
    protected $table = 'dividend_manual_history';
    protected $fillable = ['user_id', 'email', 'coin', 'market', 'filter_from', 'filter_to',
        'total_trade_volume','contest_id', 'team_id',
        'bonus_amount',
        'balance', 'status', 'bonus_currency', 'created_at', 'updated_at'];
}
