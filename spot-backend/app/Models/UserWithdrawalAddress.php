<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWithdrawalAddress extends Model
{
    public $table = 'user_withdrawal_addresses';

    public $fillable = ['user_id', 'coin', 'wallet_name', 'wallet_sub_address', 'wallet_address', 'created_at', 'updated_at'];

    public function scopeMy($query)
    {
        return $query->where('user_id', auth('api')->id());
    }

    public function network()
    {
        return $this->belongsTo(Network::class);
    }
}
