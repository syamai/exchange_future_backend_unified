<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirdropUserSetting extends Model
{
    protected $table = 'airdrop_user_settings';

    protected $primaryKey = 'user_id';

    public $fillable = [
        'user_id',
        'email',
        'period',
        'unlock_percent',
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
}
