<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KrwTransaction extends Model
{
    use HasFactory;

    public $fillable = [
        'type',
        'user_id',
        'bank_name',
        'account_name',
        'account_no',
        'exchange_rate',
        'amount_usdt',
        'amount_krw',
        'fee',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['type'])) {
            $query->where('type', $input['type']);
        }

        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (!empty($input['start_date'])) {
            $query->where('created_at', '>=', date("Y-m-d", strtotime($input['start_date'])) . ' 00:00:00');
        }

        if (!empty($input['end_date'])) {
            $query->where('created_at', '<=', date("Y-m-d", strtotime($input['end_date'])) . ' 23:59:59');
        }

        return $query;
    }
}
