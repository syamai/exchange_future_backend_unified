<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitmexMappingOrder extends Model
{
    public $table = 'bitmex_mapping_order';
    public $fillable = [
        'trade_id',
        'buy_order_id',
        'sell_order_id',
        'user_email',
        'bot_email',
        'symbol',
        'price',
        'user_order_side',
        'user_order_qty',
        'bitmex_order_id',
        'bitmex_account_id',
        'bitmex_account_email',
        'bitmex_order_side',
        'bitmex_matched_order_qty',
        'bitmex_remaining_order_qty',
        'status',
        'retry',
        'max_retry',
        'note',
    ];
}
