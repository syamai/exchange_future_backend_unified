<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    public $table = 'settings';

    public $fillable = [
        'id',
        'key',
        'value'
    ];
}
