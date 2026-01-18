<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatbotCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'type_id',
        'admin_id',
        'title_en',
        'title_vi',
        'status'
    ];

    public function subCategories()
    {
        return $this->hasMany(ChatbotSubCategory::class, 'cat_id', 'id');
    }

    public function chatbots()
    {
        return $this->hasMany(Chatbot::class, 'cat_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function type()
    {
        return $this->belongsTo(ChatbotType::class, 'type_id', 'id');
    }

    public function activityLogs() {
        return $this->morphMany(ActivityLog::class, 'object');
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (!empty($input['type_id'])) {
            $query->where('type_id', $input['type_id']);
        }

        if (!empty($input['title'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('title_en', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_vi', 'LIKE', "%{$input['title']}%");
            });
        }

        if (!empty($input['last_from']) && !empty($input['last_to'])) {
            $query->whereBetween('updated_at', [Carbon::createFromTimestampMs($input['last_from']), Carbon::createFromTimestampMs($input['last_to'])]);
        }
        return $query;
    }

}
