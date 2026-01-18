<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leaderboard extends Model
{
    public $table = 'trading_volume_ranking_total';

    public $fillable = ['user_id', 'email', 'volume', 'coin', 'created_at', 'updated_at'];

    public function scopeMy($query)
    {
        return $query->where('user_id', auth('api')->id());
    }

    /**
     * Get the user that owns the Leaderboard
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
