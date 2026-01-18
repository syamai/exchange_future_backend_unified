<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coin extends Model
{
    protected $table = 'coins';

    protected $fillable = [
        'coin',
        'icon_image',
        'name',
        'confirmation',
        'contract_address',
        'type',
        'trezor_coin_shortcut',
        'trezor_address_path',
        'env',
        'transaction_tx_path',
        'transaction_explorer',
        'decimal',
        'usd_price',
        'updated_at',
        'status',
        'is_fixed_price'
    ];

    public function scopeNetwork($query)
    {
        return $query->where('env', config('blockchain.network'));
    }

    public function networkCoins()
    {
        return $this->hasMany(NetworkCoin::class);
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['name'])) {
            $query->where('name', 'LIKE', "%{$input['name']}%");
        }
        if (!empty($input['coin'])) {
            $query->where('coin', $input['coin']);
        }
        if (!empty($input['usd_price'])) {
            $query->where('usd_price', $input['usd_price']);
        }

        return $query;
    }
}
