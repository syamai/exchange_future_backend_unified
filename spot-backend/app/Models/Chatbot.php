<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chatbot extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'admin_id',
        'type_id',
        'cat_id',
        'sub_cat_id',
        'link_page',
        'question_en',
        'question_vi',
        'answer_en',
        'answer_vi',
        'status'
    ];


    public function category()
    {
        return $this->belongsTo(ChatbotCategory::class, 'cat_id', 'id');
    }

    public function subCategory()
    {
        return $this->belongsTo(ChatbotSubCategory::class, 'sub_cat_id', 'id');
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
        if (!empty($input['question'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('question_en', 'LIKE', "%{$input['question']}%");
                $q->orWhere('question_vi', 'LIKE', "%{$input['question']}%");
            });
        }

        if (!empty($input['type_id'])) {
            $query->where('type_id', $input['type_id']);
        }

        if (!empty($input['cat_id'])) {
            $query->where('cat_id', $input['cat_id']);
        }

        if (!empty($input['sub_cat_id'])) {
            $query->where('sub_cat_id', $input['sub_cat_id']);
        }

        if (!empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (!empty($input['last_from']) && !empty($input['last_to'])) {
            $query->whereBetween('updated_at', [Carbon::createFromTimestampMs($input['last_from']), Carbon::createFromTimestampMs($input['last_to'])]);
        }
        return $query;
    }
}
