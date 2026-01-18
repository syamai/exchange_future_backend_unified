<?php

namespace App\Models;

use App\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'subject',
        'content',
        'thumbnail',
        'url',
        'status',
        'isPinned',
        'pinnedPosition',
        'starts_at',
        'expires_at',
        'deleted_at',
    ];

    // Nếu bạn dùng kiểu JSON trong DB
    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['thumbnail_full_url'];

    public function categories()
    {
        return $this->belongsToMany(PromotionCategory::class, 'promotion_category_pivot', 'promotion_id', 'promotion_category_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at')->andWhere(function ($qb) {
            $qb->whereNull('expires_at')->orWhere('expires_at', '>', now()->startOfDay());
        });
    }

    public function scopeComingSoon($query)
    {
        return $query->whereNull('deleted_at')->andWhere(function ($qb) {
            $qb->whereNull('starts_at')->orWhere('starts_at', '>', now()->startOfDay());
        });
    }

    public function scopeExpired($query)
    {
        return $query->whereNull('deleted_at')->andWhere(function ($qb) {
            $qb->whereNotNull('expires_at')->andWhere('expires_at', '<=', now()->startOfDay());
        });
    }

    public function scopeFilterByPeriod($query, $period)
    {
        return match ($period) {
            'onGoing' => $query->where('starts_at', '<=', now()->startOfDay())
                ->where(function ($qb) {
                    $qb->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now()->startOfDay());
                }),
            'comingSoon' => $query->where('starts_at', '>', now()->startOfDay()),
            'ended' => $query->where('expires_at', '<', now()->startOfDay()),
            'all' => $query,
            default => $query
        };
    }

    public function getThumbnailFullUrlAttribute()
    {
        return Utils::getImageUrl($this->thumbnail);
    }
}
