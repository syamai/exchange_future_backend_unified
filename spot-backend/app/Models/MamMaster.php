<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MamMaster extends Model
{
    public $table = 'mam_masters';
    public $fillable = [
        'account_id',
        'max_drawdown',
        'fund_balance',
        'fund_capital',
        'init_fund_balance',
        'performance_rate',
        'next_performance_rate',
        'status',
        'revokable_amount',
        'updated_interval',
        'total_revoke_amount',
        'timestamp',
    ];
}
