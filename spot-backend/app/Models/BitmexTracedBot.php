<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitmexTracedBot extends Model
{
    public $table = 'bitmex_traced_bot';
    public $fillable = [
        'email',
        'is_active',
    ];
}
