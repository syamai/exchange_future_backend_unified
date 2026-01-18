<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsNotificationCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_en',
        'title_vi',
        'status'
    ];

    public function newsNotifications() {
        return $this->hasMany(NewsNotification::class, 'cat_id', 'id');
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['title'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('title_en', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_vi', 'LIKE', "%{$input['title']}%");
            });
        }
        return $query;
    }
}
