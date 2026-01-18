<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Service\Margin\MarginBigNumber;

class Process extends Model
{
    public $table = 'processes';

    public $fillable = [
        'key',
        'processed_id',
        'is_processed',
    ];
}
