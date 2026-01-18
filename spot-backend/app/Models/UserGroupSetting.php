<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroupSetting extends Model
{
    public $table = 'user_group_setting';

    public $fillable = [
        'id',
        'name',
        'memo'
    ];
}
