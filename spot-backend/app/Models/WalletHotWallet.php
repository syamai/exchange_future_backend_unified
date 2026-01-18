<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletHotWallet extends Model
{
    protected $table = 'wallet_hot_wallet';

    protected $fillable = ['user_id', 'wallet_id', 'address', 'currency', 'secret', 'balance', 'is_external'];

    public static function getAddress($currency)
    {
        return self::where(compact('currency'))->value('address');
    }
}
