<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientReferrerHistory extends Model
{
    use HasFactory;

    protected $table = 'client_referrer_histories';
    protected $guarded = [];

    public $fillable = [
        'user_id',
        'email',
        'amount',
        'coin',
        'commission_rate',
        'transaction_owner',
        'transaction_owner_email',
        'type',
        'usdt_value',
        'complete_transaction_id',
        'executed_date',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the transaction owner (referral user)
     */
    public function transactionOwner()
    {
        return $this->belongsTo(User::class, 'transaction_owner', 'id');
    }

    public function completeTransaction() {
        return $this->belongsTo(CompleteTransaction::class, 'complete_transaction_id', 'id');
    }
}
