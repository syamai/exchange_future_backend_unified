<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMarketingHistory extends Model
{
    protected $table = 'user_marketing_histories';

    protected $fillable = ['email', 'email_marketing_id', 'sended_id', 'status', 'message'];

    protected $hidden = ['created_at', 'updated_at'];
}
