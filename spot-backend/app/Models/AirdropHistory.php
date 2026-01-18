<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirdropHistory extends Model
{
    //
    protected $table = 'airdrop_history';

    public $fillable = [
        'user_id',
        'email',
        'currency',
        'status',
        'amount',
        'last_unlocked_date'
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
