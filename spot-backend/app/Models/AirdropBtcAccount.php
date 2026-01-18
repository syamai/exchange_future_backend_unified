<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirdropBtcAccount extends Model
{
    public $table = 'airdrop_btc_accounts';
    protected $fillable = [
        'id',
        'balance',
        'usd_mount',
        'available_balance',
        'last_unlock_date'
    ];
}
