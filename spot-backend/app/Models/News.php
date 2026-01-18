<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $table = 'news';

    public $fillable = [
        'title',
        'article_id',
        'url',
        'created_at',
        'updated_at',
        'locale'
    ];
    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('is_read');
    }
}
