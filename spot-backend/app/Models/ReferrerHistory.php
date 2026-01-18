<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferrerHistory extends Model
{
    protected $table = 'referrer_histories';
    public $fillable = [
        'user_id',
        'email',
        'amount',
        'commission_rate',
        'coin',
        'order_transaction_id',
        'transaction_owner',
        'transaction_owner_email',
        'type',
        'created_at',
        'updated_at',
        'symbol',
        'asset_future',
        'is_direct_ref',
        'future_referral_message_id',
        'usdt_value',
        'complete_transaction_id',
        'executed_date'
    ];

    public function orderTransaction() {
        return $this->belongsTo(OrderTransaction::class, 'order_transaction_id', 'id');
    }
    
    public function futureReferralMessage() {
        return $this->belongsTo(FutureReferralMessage::class, 'future_referral_message_id', 'id');
    }
    
    public function completeTransaction() {
        return $this->belongsTo(CompleteTransaction::class, 'complete_transaction_id', 'id');
    }

    /**
     * Get the transaction owner (referral user)
     */
    public function transactionOwner()
    {
        return $this->belongsTo(User::class, 'transaction_owner', 'id');
    }
}
