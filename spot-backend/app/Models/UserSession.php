<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'session_id',
        'expire_at'
    ];
}
