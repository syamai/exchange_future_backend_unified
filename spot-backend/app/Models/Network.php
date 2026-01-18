<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    use HasFactory;

    public $table = 'networks';

    public $fillable = [
        'symbol',
        'name',
        'currency',
        'network_code',
        'chain_id',
        'network_deposit_enable',
        'network_withdraw_enable',
        'deposit_confirmation',
        'explorer_url',
        'enable',
        'created_at',
        'updated_at',
        'explorer_url'
    ];

    protected $hidden = [];

    public $timestamps = true;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'network_deposit_enable' => 'integer',
        'network_withdraw_enable' => 'integer',
        'enable' => 'integer',
        'deposit_confirmation' => 'integer',
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        // You can define validation rules here
    ];

    public function scopeActive($query)
    {
        return $query->where('enable', 1);
    }

    public function supportsDeposit()
    {
        return $this->network_deposit_enable === 1;
    }

    public function supportsWithdraw()
    {
        return $this->network_withdraw_enable === 1;
    }

    public function networkCoins()
    {
        return $this->hasMany(NetworkCoin::class);
    }

    public function getExplorerLink($transactionHash)
    {
        return str_replace('{tx}', $transactionHash, $this->explorer_url);
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['name'])) {
            $query->where('name', 'LIKE', "%{$input['name']}%");
        }
        if (!empty($input['symbol'])) {
            $query->where('symbol', $input['symbol']);
        }
        if (!empty($input['currency'])) {
            $query->where('currency', 'LIKE', "%{$input['currency']}%");
        }

        return $query;
    }
}
