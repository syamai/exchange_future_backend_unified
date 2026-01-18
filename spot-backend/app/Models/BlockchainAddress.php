<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockchainAddress extends Model
{
    protected $table = 'blockchain_addresses';
    protected $fillable = [
        'currency',
        'blockchain_address',
        'blockchain_sub_address',
        'device_id',
        'path',
        'address_id',
        'available'
    ];

    public static function getFirst($input)
    {
        return self::filter($input)->first();
    }

    public function scopeFilter($query, $input)
    {
        foreach ($this->fillable as $value) {
            if (isset($input[$value])) {
                $query->where($value, $input[$value]);
            }
        }
        return $query;
    }
}
