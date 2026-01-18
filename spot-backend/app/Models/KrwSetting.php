<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KrwSetting extends Model
{
    use HasFactory;

    public $fillable = [
        'id',
        'key',
        'value'
    ];
}
