<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalLimit extends Model
{
    public $table = 'withdrawal_limits';
    public $fillable = ['security_level', 'currency', 'limit', 'daily_limit', 'fee', 'minium_withdrawal', 'days'];

    private $ignoreParams = ['page', 'limit'];

    public function scopeFilter($query, $input)
    {
        foreach ($this->fillable as $value) {
            if (!isset($input[$value]) || in_array($value, $this->ignoreParams)) {
                continue;
            }
            $query->where($value, $input[$value]);
        }
        if (isset($input['search_key'])) {
            $query->where('currency', "LIKE", "%{$input['search_key']}%");
        }
        if (isset($input['sort']) && isset($input['sort_type'])) {
            $query->orderBy($input['sort'], $input['sort_type']);
        }
        return $query;
    }
}
