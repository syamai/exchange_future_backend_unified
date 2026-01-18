<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'coin_pair',
        'created_at',
        'updated_at'
    ];

    protected $hidden = ['created_at', 'updated_at'];
}
