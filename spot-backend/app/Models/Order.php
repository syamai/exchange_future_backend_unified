<?php

namespace App\Models;

use App\Consts;
use App\Utils\BigNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    public $table = 'orders';

    public $fillable = [
        'user_id',
        'email',
        'trade_type',
        'currency',
        'coin',
        'type',
        'ioc',
        'quantity',
        'price',
        'executed_quantity',
        'executed_price',
        'base_price',
        'stop_condition',
        'fee',
        'status',
        'created_at',
        'updated_at',
        'reverse_price',
        'market_type'
    ];

    protected $hidden = ['reverse_price'];

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
    public function scopeMy($query)
    {
        return $query->where('user_id', auth('api')->id());
    }

    public function isCanceled()
    {
        return $this->status === Consts::ORDER_STATUS_CANCELED;
    }

    public function canMatching()
    {
        return $this->status === Consts::ORDER_STATUS_PENDING || $this->status === Consts::ORDER_STATUS_EXECUTING;
    }

    public function canCancel()
    {
        return $this->status == Consts::ORDER_STATUS_NEW
            || $this->status == Consts::ORDER_STATUS_PENDING
            || $this->status == Consts::ORDER_STATUS_STOPPING
            || $this->status == Consts::ORDER_STATUS_EXECUTING;
    }

    public function getRemaining()
    {
        return BigNumber::new($this->quantity)->sub($this->executed_quantity)->toString();
    }


    public function isStoppingOrder()
    {
        return $this->status === Consts::ORDER_STATUS_STOPPING;
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
