<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    public $table = 'user_group';

    public $fillable = [
        'group_id',
        'user_id'
    ];
}
