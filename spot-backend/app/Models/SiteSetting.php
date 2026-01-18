<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $table = 'site_settings';
    public $fillable = [
        'app_name', 'short_name', 'site_email',
        'site_phone_number', 'language', 'copyright', 'social',
        'ios_app_link', 'android_app_link','banner', 'footer'
    ];

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
