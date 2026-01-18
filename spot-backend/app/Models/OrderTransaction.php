<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTransaction extends Model
{
    public $table = 'order_transactions';

    public $fillable = [

    ];

    public $timestamps = false;

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function scopeOrderTransactions($query, $user_id) {
        return $query->where(function ($query) use($user_id) { return $query->where('seller_id', $user_id)->orWhere('buyer_id', $user_id);});
    }
}
