<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocicalNetwork extends Model
{
    protected $table = 'social_networks';

    protected $fillable = ['name', 'icon_class', 'type', 'link', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];
}
