<?php

namespace App\Models;

use App\Utils;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    public $fillable = [
        'title',
        'banner_url',
        'banner_mobile_url',
        'support_url',
        'started_at',
        'ended_at',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    protected $appends = ['banner_full_url', 'banner_mobile_full_url'];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function getBannerFullUrlAttribute()
    {
        return Utils::getImageUrl($this->banner_url);
    }

    public function getBannerMobileFullUrlAttribute()
    {
        return Utils::getImageUrl($this->banner_mobile_url);
    }
}
