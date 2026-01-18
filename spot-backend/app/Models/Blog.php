<?php

namespace App\Models;

use App\Utils;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'admin_id',
        'cat_id',
        'is_pin',
        'static_url',
        'thumbnail_url',
        'title_en',
        'seo_title_en',
        'meta_keywords_en',
        'seo_description_en',
        'content_en',
        'title_vi',
        'seo_title_vi',
        'meta_keywords_vi',
        'seo_description_vi',
        'content_vi',
        'status',
    ];
    protected $appends = ['thumbnail_full_url'];

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'cat_id', 'id');
    }

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
        if (!empty($input['last_from']) && !empty($input['last_to'])) {
            $query->whereBetween('updated_at', [Carbon::createFromTimestampMs($input['last_from']), Carbon::createFromTimestampMs($input['last_to'])]);
        }

        if (!empty($input['title'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('title_en', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_vi', 'LIKE', "%{$input['title']}%");
            });
        }

        if (!empty($input['posted_from']) && !empty($input['posted_to'])) {
            $query->whereBetween('created_at', [Carbon::createFromTimestampMs($input['posted_from']), Carbon::createFromTimestampMs($input['posted_to'])]);
        }

        if (!empty($input['cat_id'])) {
            $query->where('cat_id', $input['cat_id']);
        }

        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        return $query;
    }
}
