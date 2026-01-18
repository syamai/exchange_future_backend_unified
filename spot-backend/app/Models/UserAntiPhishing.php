<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAntiPhishing extends Model
{
    protected $table = 'user_anti_phishing';

    protected $fillable = [
        'user_id',
        'is_anti_phishing',
        'anti_phishing_code',
        'is_active',
    ];

    public function user($query)
    {
        return $this->belongsTo(User::class);
    }
}
