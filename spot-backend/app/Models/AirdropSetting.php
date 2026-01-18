<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirdropSetting extends Model
{
    protected $table = 'airdrop_settings';

    public $fillable = [
        'id',
        'enable',
        'currency',
        'period',
        'unlock_percent',
        'payout_amount',
        'payout_time',
        'remaining',
        'total_paid',
        'total_supply',
        'min_hold_amal',
        'status',
        'enable_fee_amal',
        'created_at',
        'updated_at',
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

    public function getTableName()
    {
        return $this->table;
    }
}
