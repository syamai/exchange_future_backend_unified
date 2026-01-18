<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MamSetting extends Model
{
    public $table = 'mam_settings';
    public $fillable = [
        'key',
        'value',
        'timestamp',
    ];
}
