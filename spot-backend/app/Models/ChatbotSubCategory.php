<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotSubCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_en',
        'title_vi'
    ];

    public function category()
    {
        return $this->belongsTo(ChatbotCategory::class, 'cat_id', 'id');
    }

    public function chatbots()
    {
        return $this->hasMany(Chatbot::class, 'sub_cat_id', 'id');
    }
}
