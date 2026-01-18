<?php

namespace App\Http\Services;

use App\Enums\PromotionCategoryStatus;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Models\PromotionCategory;
use App\Utils;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Carbon\Carbon;

class PromotionService
{
    const MAXIMUM_PINNED_PROMOTION = 5;
    public function adminGetPromotions($params)
    {
        $period = $params['period'] ?? 'all';
        $categories = $params['categories'] ?? null;
        $key = $params['key'] ?? null;
        $size = $params['size'] ?? 10;
        $locale = $params['locale'] ?? 'en';
        $postedDateFrom = $params['posted_date_from'] ?? null;
        $postedDateTo = $params['posted_date_to'] ?? null;
        $updatedDateFrom = $params['updated_date_from'] ?? null;
        $updatedDateTo = $params['updated_date_to'] ?? null;
        $status = $params['status'] ?? null;

        $promotions = Promotion::withoutTrashed()
            ->with('categories')
            ->filterByPeriod($period)
            ->when(!empty($categories), function ($query) use ($categories) {
                $query->whereHas('categories', function ($q) use ($categories) {
                    $q->whereIn('promotion_categories.id', (array)$categories);
                });
            })
            ->when(!empty($key), function ($query) use ($key, $locale) {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(subject, '$.{$locale}')) LIKE ?", ["%{$key}%"]);
            })
            ->when(!empty($status), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when(!empty($postedDateFrom), function ($query) use ($postedDateFrom) {
                $query->whereDate('created_at', '>=', $postedDateFrom);
            })
            ->when(!empty($postedDateTo), function ($query) use ($postedDateTo) {
                $query->whereDate('created_at', '<=', $postedDateTo);
            })
            ->when(!empty($updatedDateFrom), function ($query) use ($updatedDateFrom) {
                $query->whereDate('updated_at', '>=', $updatedDateFrom);
            })
            ->when(!empty($updatedDateTo), function ($query) use ($updatedDateTo) {
                $query->whereDate('updated_at', '<=', $updatedDateTo);
            })
            ->orderByDesc('isPinned')
            ->orderBy('pinnedPosition')
            ->orderByDesc('id')
            ->paginate($size);

        $promotions->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'subject' => json_decode($item->subject),
                'content' => json_decode($item->content),
                'thumbnail' => $item->thumbnail,
                'thumbnail_full_url' => $item->thumbnail_full_url,
                'url' => $item->url,
                'categories' => $item->categories ? $item->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => json_decode($category->name),
                        'key' => $category->key,
                        'status' => $category->status
                    ];
                }) : [],
                'status' => $item->status,
                'isPinned' => $item->isPinned,
                'pinnedPosition' => $item->pinnedPosition,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        $filter = [
            'period' => [
                'all' => 'All',
                'onGoing' => 'On Going',
                'comingSoon' => 'Coming Soon',
                'ended' => 'Ended',
            ],
            'categories' => collect([
                    [
                        'id' => '',
                        'name' => '{"en":"All","vi":"Tất cả"}',
                        'key' => null
                    ]
                ])
                ->merge(
                    PromotionCategory::where('status', PromotionCategoryStatus::ACTIVE)
                        ->get()
                        ->map(function ($category) {
                            return [
                                'id' => $category->id,
                                'name' => $category->name,
                                'key' => $category->key
                            ];
                        })
                )
                ->toArray()
        ];

        return [
            'filter' => $filter,
            'list' => $promotions
        ];
    }

    public function getPromotions($params)
    {
        $period = $params['period'] ?? 'onGoing';
        $categories = $params['categories'] ?? null;
        $key = $params['key'] ?? null;
        $size = $params['size'] ?? 10;
        $locale = $params['locale'] ?? 'en';
        $postedDateFrom = $params['posted_date_from'] ?? null;
        $postedDateTo = $params['posted_date_to'] ?? null;
        $updatedDateFrom = $params['updated_date_from'] ?? null;
        $updatedDateTo = $params['updated_date_to'] ?? null;
        $status = $params['status'] ?? null;

        $promotions = Promotion::withoutTrashed()
            ->with('categories')
            ->filterByPeriod($period)
            ->whereDoesntHave('categories', function ($q) {
                $q->where('promotion_categories.status', PromotionCategoryStatus::INACTIVE);
            })
            ->when(!empty($categories), function ($query) use ($categories) {
                $query->whereHas('categories', function ($q) use ($categories) {
                    $q->whereIn('promotion_categories.id', (array)$categories);
                });
            })
            ->when(!empty($key), function ($query) use ($key, $locale) {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(subject, '$.{$locale}')) LIKE ?", ["%{$key}%"]);
            })
            ->when(!empty($status), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when(!empty($postedDateFrom), function ($query) use ($postedDateFrom) {
                $query->whereDate('created_at', '>=', $postedDateFrom);
            })
            ->when(!empty($postedDateTo), function ($query) use ($postedDateTo) {
                $query->whereDate('created_at', '<=', $postedDateTo);
            })
            ->when(!empty($updatedDateFrom), function ($query) use ($updatedDateFrom) {
                $query->whereDate('updated_at', '>=', $updatedDateFrom);
            })
            ->when(!empty($updatedDateTo), function ($query) use ($updatedDateTo) {
                $query->whereDate('updated_at', '<=', $updatedDateTo);
            })
            ->orderByDesc('isPinned')
            ->orderBy('pinnedPosition')
            ->orderByDesc('id')
            ->paginate($size);

        $promotions->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'subject' => json_decode($item->subject),
                'content' => json_decode($item->content),
                'thumbnail' => $item->thumbnail,
                'thumbnail_full_url' => $item->thumbnail_full_url,
                'url' => $item->url,
                'categories' => $item->categories ? $item->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => json_decode($category->name),
                        'key' => $category->key,
                        'status' => $category->status
                    ];
                }) : [],
                'status' => $item->status,
                'isPinned' => $item->isPinned,
                'pinnedPosition' => $item->pinnedPosition,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        $filter = [
            'period' => [
                'onGoing' => 'On Going',
                'comingSoon' => 'Coming Soon',
                'ended' => 'Ended',
            ],
            'categories' => collect([
                    [
                        'id' => '',
                        'name' => '{"en":"All","vi":"Tất cả"}',
                        'key' => null
                    ]
                ])
                ->merge(
                    PromotionCategory::where('status', PromotionCategoryStatus::ACTIVE)
                        ->get()
                        ->map(function ($category) {
                            return [
                                'id' => $category->id,
                                'name' => $category->name,
                                'key' => $category->key
                            ];
                        })
                )
                ->toArray()
        ];

        return [
            'filter' => $filter,
            'list' => $promotions
        ];
    }

    public function getPromotionDetails($promotion)
    {
        return new PromotionResource($promotion);
    }

    public function createPromotion($params)
    {
        $promotion = new Promotion;
        $promotion->subject = is_string($params['subject']) ? $params['subject'] : json_encode($params['subject']);
        $promotion->content = is_string($params['content']) ? $params['content'] : json_encode($params['content']);
        $promotion->url = $params['url'];
        $promotion->isPinned = $params['isPinned'] ?? false;
        $promotion->status = $params['status'];

        if (!empty($params['expires_at'])) {
            $promotion->expires_at = Carbon::parse($params['expires_at'])->endOfDay();
        }
        if (!empty($params['starts_at'])) {
            $promotion->starts_at = Carbon::parse($params['starts_at'])->startOfDay();
        }
        if (!empty($params['thumbnail'])) {
            $promotion->thumbnail = $this->handleThumbnailUpload($params['thumbnail']);
        }

        $promotion->save();

        // Attach categories
        if (!empty($params['categories'])) {
            $promotion->categories()->attach($params['categories']);
        }

        return $promotion;
    }

    public function updatePromotion($params, $promotion)
    {
        // Handle JSON fields
        if (isset($params['subject'])) {
            $params['subject'] = is_string($params['subject']) ? $params['subject'] : json_encode($params['subject']);
        }
        if (isset($params['content'])) {
            $params['content'] = is_string($params['content']) ? $params['content'] : json_encode($params['content']);
        }

        // Handle thumbnail upload
        if (isset($params['thumbnail'])) {
            $params['thumbnail'] = $this->handleThumbnailUpload($params['thumbnail']);
        }

        // Handle categories
        if (isset($params['categories'])) {
            $promotion->categories()->sync($params['categories']);
            unset($params['categories']);
        }
        
        $filteredParams = array_filter($params, function ($value) {
            return $value !== '' && $value !== null;
        });
        $promotion->fill($filteredParams)->save();

        return $promotion;
    }

    private function handleThumbnailUpload($thumbnail)
    {
        if (is_file($thumbnail)) {
            return Utils::saveFileToStorage($thumbnail, 'promotions', null, 'public');
        }
        return $thumbnail;
    }

    public function deletePromotion($promotion)
    {
        $promotion->delete();

        return $promotion;
    }

    public function pinPromotion($params, Promotion $promotion)
    {
        // Check if already pinned maximum items
        $pinnedCount = Promotion::where('isPinned', true)->count();
        if ($pinnedCount >= self::MAXIMUM_PINNED_PROMOTION) {
            throw new HttpException('403', 'promotion.maximum_pinned');
        }

        if ($params['isPinned']) {
            // Increase current pinned position
            Promotion::where('isPinned', true)
                ->where('pinnedPosition', '<', 5)
                ->increment('pinnedPosition');
        }

        $promotion->isPinned = $params['isPinned'];
        $promotion->pinnedPosition = $params['isPinned'] ? 0 : null;
        $promotion->save();

        return $promotion;
    }
}
