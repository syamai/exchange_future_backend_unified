<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirdropEthAccount extends Model
{
    public $table = 'airdrop_eth_accounts';
    protected $fillable = [
        'id',
        'balance',
        'usd_mount',
        'available_balance',
        'last_unlock_date'
    ];
}
