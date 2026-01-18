<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TotalPrice extends Model
{
    public $table = 'total_prices';


    public $timestamps = false;

    protected $guarded = [];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];
}
