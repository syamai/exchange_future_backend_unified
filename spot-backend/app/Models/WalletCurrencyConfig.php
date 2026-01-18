<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletCurrencyConfig extends Model
{
    protected $table = 'wallet_currency_config';

    protected $fillable = [ 'currency', 'network', 'chain_id', 'chain_name', 'average_block_time', 'required_confirmations',
        'internal_endpoint', 'rpc_endpoint', 'rest_endpoint', 'explorer_endpoint'
    ];


    public function scopeNetwork($query)
    {
        return $query->where('network', config('blockchain.network'));
    }

    public static function getApiCreateAddress($currency)
    {
        return self::network()
            ->where('currency', $currency)
            ->value('internal_endpoint');
    }
}
