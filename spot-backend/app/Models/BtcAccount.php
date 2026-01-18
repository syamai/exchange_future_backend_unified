<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BtcAccount extends Model
{
    public $fillable = [
        'id',
        'balance',
        'available_balance',
        'blockchain_address'
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

    public function scopeMy($query)
    {
        return $query->where('id', auth('api')->id());
    }
}
