<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmalTransaction extends Model
{
    use HasFactory;

    public $table = 'amal_transactions';
    public $fillable = [
        'user_id',
        'amount',
        'currency',
        'bonus',
        'total',
        'price',
        'price_bonus',
        'payment'
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
