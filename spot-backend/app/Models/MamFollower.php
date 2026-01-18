<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MamFollower extends Model
{
    public $table = 'mam_followers';
    public $fillable = [
        'master_id',
        'user_id',
        'user_capital',
        'user_balance',
        'init_user_balance',
        'performance_fee',
        'joined_at',
        'left_at',
        'timestamp',
    ];
}
