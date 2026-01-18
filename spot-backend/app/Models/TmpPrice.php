<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TmpPrice extends Model
{
    public $table = 'tmp_prices';

    public $fillable = [
    ];

    public $timestamps = false;

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
