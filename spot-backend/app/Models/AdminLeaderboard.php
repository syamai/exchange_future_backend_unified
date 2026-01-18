<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLeaderboard extends Model
{
    public $table = 'trading_volume_ranking';

    public $fillable = ['user_id', 'email', 'volume', 'coin', 'created_at', 'updated_at'];

    public function scopeMy($query)
    {
        return $query->where('user_id', auth('api')->id());
    }
}
