<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function scopeFilter($query, $input)
    {
        if (!empty($input['name'])) {
            $query->where('name', 'LIKE', "%{$input['name']}%");
        }
        return $query;
    }
}
