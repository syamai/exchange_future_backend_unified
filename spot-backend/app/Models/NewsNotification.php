<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsNotification extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'admin_id',
        'cat_id',
        'sub_cat_id',
        'title_en',
        'title_vi',
        'title_ko',
        'content_en',
        'content_vi',
        'content_ko',
        'link_event',
        'status'
    ];


    public function category()
    {
        return $this->belongsTo(NewsNotificationCategory::class, 'cat_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function readers()
    {
        return $this->belongsToMany(User::class, 'news_notification_users')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function activityLogs() {
        return $this->morphMany(ActivityLog::class, 'object');
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['title'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('title_en', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_vi', 'LIKE', "%{$input['title']}%");
            });
        }
        if (!empty($input['cat_id'])) {
            $query->where('cat_id', $input['cat_id']);
        }
        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }
        if (!empty($input['last_from']) && !empty($input['last_to'])) {
            $query->whereBetween('updated_at', [Carbon::createFromTimestampMs($input['last_from']), Carbon::createFromTimestampMs($input['last_to'])]);
        }
        if (!empty($input['posted_from']) && !empty($input['posted_to'])) {
            $query->whereBetween('created_at', [Carbon::createFromTimestampMs($input['posted_from']), Carbon::createFromTimestampMs($input['posted_to'])]);
        }
        return $query;
    }

}
