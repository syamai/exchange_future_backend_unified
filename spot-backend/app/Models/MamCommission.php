<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MamCommission extends Model
{
    public $table = 'mam_commission';
    public $fillable = [
        'master_id',
        'interval',
        'entry_capital',
        'exit_capital',
        'entry_balance',
        'exit_balance',
        'followers',
        'commission',
        'realised_pnl',
        'timestamp',
    ];
}
