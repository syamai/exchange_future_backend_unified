<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkCoin extends Model
{
    use HasFactory;

    public $table = 'network_coins';

    public $fillable = [
        'network_id',
        'coin_id',
        'contract_address',
        'network_deposit_enable',
        'network_withdraw_enable',
        'network_enable',
        'token_explorer_url',
        'withdraw_fee',
        'min_deposit',
        'min_withdraw',
        'created_at',
        'updated_at',
        'token_explorer_url',
        'decimal'
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
        'network_enable' => 'integer',
        'withdraw_fee' => 'float',
        'min_deposit' => 'float',
        'min_withdraw' => 'float'
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        // Define validation rules here if necessary
    ];

    public function scopeActive($query)
    {
        return $query->where('network_enable', 1);
    }

    public function supportsDeposit()
    {
        return $this->network_deposit_enable === 1;
    }

    public function supportsWithdraw()
    {
        return $this->network_withdraw_enable === 1;
    }

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin_id', 'id');
    }

    public function network()
    {
        return $this->belongsTo(Network::class);
    }

    public function getExplorerLink($transactionHash)
    {
        return str_replace('{tx}', $transactionHash, $this->token_explorer_url);
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['coin_id'])) {
            $query->where('coin_id', $input['coin_id']);
        }
        if (!empty($input['network_id'])) {
            $query->where('network_id', $input['network_id']);
        }

        return $query;
    }
}
