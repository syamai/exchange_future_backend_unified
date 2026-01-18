<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnableTradingSetting extends Model
{
    public $table = 'enable_trading_settings';

    public $fillable = [
        'currency',
        'coin',
        'email',
        'enable_trading',
        'ignore_expired_at',
        'is_beta_tester'
    ];
}
