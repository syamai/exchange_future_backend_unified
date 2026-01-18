<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnableFeeSetting extends Model
{
    public $table = 'enable_fee_settings';

    public $fillable = [
        'currency',
        'coin',
        'email',
        'enable_fee',
    ];
}
