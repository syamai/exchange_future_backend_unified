<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MultiReferrerDetails extends Model
{
    protected $table = 'referrer_multi_level_details';
    public $fillable = [
        'user_id',
        'referrer_id_lv_1',
        'referrer_id_lv_2',
        'referrer_id_lv_3',
        'referrer_id_lv_4',
        'referrer_id_lv_5',
        'number_of_referrer_lv_1',
        'number_of_referrer_lv_2',
        'number_of_referrer_lv_3',
        'number_of_referrer_lv_4',
        'number_of_referrer_lv_5',
    ];

    public function getNumberReferrer($value)
    {
        return $this->attributes["number_of_referrer_lv_{$value}"];
    }
}
