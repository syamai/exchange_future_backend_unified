<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceGroup extends Model
{
    public $table = 'price_groups';

    public $fillable = [
        'id',
        'currency',
        'coin',
        'group',
        'value',
        'unit'
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
