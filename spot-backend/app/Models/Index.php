<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Index extends Model
{
    public $table = 'indices';

    public $fillable = [
      'symbol',
      'value',
      'previous_value',
      'previous_24h_value',
      'updated_at',
      'created_at',
    ];
}
