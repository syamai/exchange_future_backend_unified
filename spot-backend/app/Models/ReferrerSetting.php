<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferrerSetting extends Model
{
    protected $table = 'referrer_settings';
    protected $fillable = [
        'enable',
        'number_of_levels',
        'refund_rate',
        'refund_percent_at_level_1',
        'refund_percent_at_level_2',
        'refund_percent_at_level_3',
        'refund_percent_at_level_4',
        'refund_percent_at_level_5',
        'refund_percent_in_next_program_lv_1',
        'refund_percent_in_next_program_lv_2',
        'refund_percent_in_next_program_lv_3',
        'refund_percent_in_next_program_lv_4',
        'refund_percent_in_next_program_lv_5',
        'number_people_in_next_program',
    ];
}
