<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinsConfirmation extends Model
{

    protected $table = 'coins_confirmation';
    protected $fillable = ['coin', 'confirmation', 'is_withdraw', 'is_deposit'];

    public function scopeFilter($query, $input)
    {
        foreach ($this->fillable as $value) {
            if (isset($input[$value])) {
                $query->where($value, $input[$value]);
            }
        }
        return $query;
    }

    public function scopeNetwork($query)
    {
        return $query->where('env', config('blockchain.network'));
    }

    public function coinSetting() {
        return $this->hasMany(CoinSetting::class, 'coin', 'coin');
    }
}
