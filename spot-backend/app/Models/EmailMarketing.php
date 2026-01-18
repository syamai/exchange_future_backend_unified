<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailMarketing extends Model
{
    public $fillable = [
        'title',
        'content',
		'from_email'
    ];
}
