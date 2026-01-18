<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSecuritySetting extends Model
{

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'security_level',
        'email_verified',
        'email_verification_code',
        'confirmation_code',
        'use_fake_name'
    ];

    protected $hidden = [
        'email_verification_code',
        'phone_verification_code'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
