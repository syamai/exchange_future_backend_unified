<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitmexMappingSetting extends Model
{
    public $table = 'bitmex_mapping_setting';
    public $fillable = [
        'key',
        'value',
    ];
}
