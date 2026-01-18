<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmalCashBack extends Model
{
    public $table = 'amal_cash_back';
    public $fillable = [
        'user_id',
        'referred_user_id',
        'referrer_email',
        'referred_email',
        'rate',
        'currency',
        'bonus'
    ];

    public function scopeFilter($query, $input)
    {
        foreach ($this->fillable as $value) {
            if (isset($input[$value])) {
                $query->where($value, $input[$value]);
            }
        }
        if (isset($input['start_date'])) {
            $query->where('created_at', '>=', $input['start_date']);
        }
        if (isset($input['end_date'])) {
            $query->where('created_at', '<=', $input['end_date'] . ' 23:59:59');
        }
        return $query;
    }
}
