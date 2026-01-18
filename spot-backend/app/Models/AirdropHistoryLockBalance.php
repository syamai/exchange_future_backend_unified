<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirdropHistoryLockBalance extends Model
{
    protected $table = 'airdrop_history_lock_balance';

    public $fillable = [
        'user_id',
        'email',
        'currency',
        'status',
        'total_balance',
        'amount',
        'unlocked_balance',
        'last_unlocked_date',
        'type',
    ];
    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];
}
