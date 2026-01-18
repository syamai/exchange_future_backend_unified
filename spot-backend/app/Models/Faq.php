<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'cat_id',
        'sub_cat_id',
        'title_en',
        'title_vi',
        'title_ko',
        'content_en',
        'content_vi',
        'content_ko',
        'status'
    ];


    public function category()
    {
        return $this->belongsTo(FaqCategory::class, 'cat_id', 'id');
    }

    public function subCategory()
    {
        return $this->belongsTo(FaqSubCategory::class, 'sub_cat_id', 'id');
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['title'])) {
            $query->where(function ($q) use ($input){
                $q->orWhere('title_en', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_vi', 'LIKE', "%{$input['title']}%");
                $q->orWhere('title_ko', 'LIKE', "%{$input['title']}%");
            });
        }
        if (!empty($input['cat_id'])) {
            $query->where('cat_id', $input['cat_id']);
        }

        if (!empty($input['sub_cat_id'])) {
            $query->where('sub_cat_id', $input['sub_cat_id']);
        }
        return $query;
    }
}
