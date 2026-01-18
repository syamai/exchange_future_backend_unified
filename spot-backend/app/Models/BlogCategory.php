<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'admin_id',
        'title_en',
        'title_vi',
        'status'
    ];


    public function blogs()
    {
        return $this->hasMany(Blog::class, 'cat_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function activityLogs() {
        return $this->morphMany(ActivityLog::class, 'object');
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['last_from']) && !empty($input['last_to'])) {
            $query->whereBetween('updated_at', [Carbon::createFromTimestampMs($input['last_from']), Carbon::createFromTimestampMs($input['last_to'])]);
        }

        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (!empty($input['title'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('title_en', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_vi', 'LIKE', "%{$input['title']}%");
            });
        }

        return $query;
    }
}
