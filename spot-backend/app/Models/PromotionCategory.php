<?php

namespace App\Models;

use App\Enums\PromotionCategoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromotionCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'key',
        'status',
        'updated_by'
    ];

    protected $casts = [
        'status' => PromotionCategoryStatus::class
    ];

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_category_pivot', 'promotion_category_id', 'promotion_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
} 