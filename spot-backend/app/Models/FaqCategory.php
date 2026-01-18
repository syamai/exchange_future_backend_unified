<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_en',
        'title_vi',
        'title_ko',
        'status'
    ];

    public function subCategories()
    {
        return $this->hasMany(FaqSubCategory::class, 'cat_id', 'id');
    }

    public function faqs()
    {
        return $this->hasMany(Faq::class, 'cat_id', 'id');
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
        return $query;
    }
}
