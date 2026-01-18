<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexSetting extends Model
{
    public $table = 'indices_settings';

    public $fillable = [
        'symbol',
        'root_symbol',
        'status',
        'type',
        'precision',
        'value',
        'constance_value',
        'previous_value',
        'previous_24h_value',
        'is_index_price',
        'reference_symbol',
        'timestamps'
    ];

    // public function getValueAttribute($value)
    // {
    //     return number_format($value, $this->precision);
    // }

    // public function getConstanceValueAttribute($value)
    // {
    //     return number_format($value, $this->precision);
    // }

    // public function getPreviousValueAttribute($value)
    // {
    //     return number_format($value, $this->precision);
    // }

    // public function getPrevious24hValueAttribute($value)
    // {
    //     return number_format($value, $this->precision);
    // }
}
