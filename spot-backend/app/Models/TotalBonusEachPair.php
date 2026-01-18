<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TotalBonusEachPair extends Model
{
    protected $table = 'dividend_total_paid_each_pairs';

    protected $fillable = ['currency', 'coin', 'total_paid', 'payout_coin'];
}
