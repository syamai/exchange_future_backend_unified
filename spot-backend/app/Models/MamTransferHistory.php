<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MamTransferHistory extends Model
{
    public $table = 'mam_transfer_history';
    public $fillable = [
        'user_id',
        'master_id',
        'amount',
        'reason',
    ];
}
