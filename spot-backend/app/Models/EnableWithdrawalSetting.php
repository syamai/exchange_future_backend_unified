<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnableWithdrawalSetting extends Model
{
    public $table = 'enable_withdrawal_settings';

    public $fillable = [
        'currency',
        'coin',
        'email',
        'enable_withdrawal',
    ];
}
