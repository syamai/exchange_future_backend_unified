<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFeeLevel extends Model
{
    protected $fillable = ['active_time', 'user_id', 'fee_level'];
}
