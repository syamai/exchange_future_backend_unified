<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInformation extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'birthday',
        'tel',
        'building_room',
        'address',
        'city',
        'state_region',
        'zip_code',
        'country_id',
    ];
}
