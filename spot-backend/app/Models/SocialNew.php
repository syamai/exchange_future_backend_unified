<?php

namespace App\Models;

use App\Utils;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialNew extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'admin_id',
        'title_en',
        'title_vi',
        'title_ko',
        'content_en',
        'content_vi',
        'content_ko',
        'link_page',
        'domain_name',
        'thumbnail_url',
        'status',
        'is_pin'
    ];

    protected $appends = ['thumbnail_full_url'];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }


    public function activityLogs() {
        return $this->morphMany(ActivityLog::class, 'object');
    }

    public function getThumbnailFullUrlAttribute()
    {
        return Utils::getImageUrl($this->thumbnail_url);
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['title'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('title_en', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_vi', 'LIKE', "%{$input['title']}%");
            });
        }
        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }
        if (!empty($input['is_pin'])) {
            $query->where('is_pin', $input['is_pin']);
        }
        if (!empty($input['last_from']) && !empty($input['last_to'])) {
            $query->whereBetween('updated_at', [Carbon::createFromTimestampMs($input['last_from']), Carbon::createFromTimestampMs($input['last_to'])]);
        }

        return $query;
    }
}
