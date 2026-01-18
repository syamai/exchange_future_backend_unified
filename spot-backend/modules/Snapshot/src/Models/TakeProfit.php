<?php

namespace Snapshot\Models;

use Illuminate\Database\Eloquent\Model;

class TakeProfit extends Model
{
    protected $table = 'take_profits';

    protected $fillable = ['amount', 'currency'];

    public function scopeFilter($query, $input)
    {
        foreach ($this->fillable as $value) {
            if (isset($input[$value])) {
                $query->where($value, $input[$value]);
            }
        }
    }
}
