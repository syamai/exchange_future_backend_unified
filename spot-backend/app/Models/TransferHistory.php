<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TransferHistory extends Model
{
    public $table = 'transfer_history';
    public $fillable = [
        'user_id',
        'email',
        'coin',
        'source',
        'destination',
        'amount',
        'before_balance',
        'after_balance'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'id', 'user_id');
    }
}
