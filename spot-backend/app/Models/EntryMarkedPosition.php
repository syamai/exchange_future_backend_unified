<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryMarkedPosition extends Model
{
    protected $table = 'entry_marked_positions';

    protected $fillable = ['account_id','contest_id','symbol','current_qty','is_locked'];
}
