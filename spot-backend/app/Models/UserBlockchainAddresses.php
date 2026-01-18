<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBlockchainAddresses extends Model
{
    use HasFactory;

    protected $table = 'user_blockchain_addresses';
    protected $fillable = [
        'currency',
        'blockchain_address',
        'user_id'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
