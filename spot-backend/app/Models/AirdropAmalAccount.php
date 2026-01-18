<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Consts;
use App\Utils\BigNumber;

class AirdropAmalAccount extends Model
{
    public $table = 'airdrop_amal_accounts';
    protected $fillable = [
        'id',
        'balance',
        'usd_mount',
        'available_balance',
        'last_unlock_date',
        'balance_bonus',
        'available_balance_bonus',
    ];
    public function scopeGetBalanceFollowType($query, $type, $amount)
    {
        if ($type == Consts::PERPETUAL_DIVIDEND_BALANCE) {
            return $query->where('balance_bonus', ">=", $amount);
        }
        if ($type == Consts::DIVIDEND_BALANCE) {
            return $query->where('balance', ">=", $amount);
        };
        return $query->whereRaw("balance + balance_bonus", ">=", $amount);
    }
}
