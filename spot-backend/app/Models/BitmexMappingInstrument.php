<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitmexMappingInstrument extends Model
{
    public $table = 'bitmex_mapping_instrument';
    public $fillable = [
        'instrument',
        'bitmex_instrument',
    ];
}
