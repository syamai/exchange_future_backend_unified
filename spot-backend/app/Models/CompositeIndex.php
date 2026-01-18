<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompositeIndex extends Model
{
    public $table = 'composite_indices';

    public $fillable = [
        'timestamp',
        'symbol',
        'index_symbol',
        'reference',
        'weight',
        'value'
    ];
}
