<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColdWalletSetting extends Model
{
    public $table = 'cold_wallet_setting';

    public $fillable = [
        'id',
        'coin',
        'address',
        'sub_address',
        'min_balance',
        'max_balance'
    ];
}
