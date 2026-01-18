<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'user_id',
        'channel',
        'auth_key',
        'is_enable'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
