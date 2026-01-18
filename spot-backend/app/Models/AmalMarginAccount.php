<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmalMarginAccount extends Model
{
    public $table = 'amal_margin_accounts';

    public $fillable = [
        'balance',
        'available_balance',
        'owner_id',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
