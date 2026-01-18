<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqSubCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_en',
        'title_vi',
        'title_ko'
    ];

    public function category()
    {
        return $this->belongsTo(FaqCategory::class, 'cat_id', 'id');
    }

    public function faqs()
    {
        return $this->hasMany(Faq::class, 'sub_cat_id', 'id');
    }
}
