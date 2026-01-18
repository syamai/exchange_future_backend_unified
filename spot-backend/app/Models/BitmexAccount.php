<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitmexAccount extends Model
{
    public $table = 'bitmex_account';
    public $fillable = [
        'account_id',
        'email',
        'key_name',
        'key_id',
        'key_secret',
    ];
}
