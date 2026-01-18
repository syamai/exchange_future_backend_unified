<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryReward extends Model
{
    use HasFactory;

    public $table = 'history_rewards';
    public $fillable = [
        'voucher_id',
        'user_id',
        'reward',
    ];
}
