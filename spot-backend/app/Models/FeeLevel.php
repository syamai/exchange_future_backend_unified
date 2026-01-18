<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeLevel extends Model
{
    public $table = 'fee_levels';
    public $fillable = ['level', 'amount', 'mgc_amount', 'fee_taker', 'fee_maker'];

    private $ignoreParams = ['page', 'limit'];

    public function scopeFilter($query, $input)
    {
        foreach ($this->fillable as $value) {
            if (!isset($input[$value]) || in_array($value, $this->ignoreParams)) {
                continue;
            }
            $query->where($value, $input[$value]);
        }
        if (isset($input['sort']) && isset($input['sort_type'])) {
            $query->orderBy($input['sort'], $input['sort_type']);
        }
        return $query;
    }

    public function coin()
    {
        $this->belongsTo(CoinsConfirmation::class, 'id', 'id');
    }
}
