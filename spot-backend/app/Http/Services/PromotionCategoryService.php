<?php

namespace App\Http\Services;

use App\Models\PromotionCategory;
use Illuminate\Support\Str;

class PromotionCategoryService
{
    public function getCategories($params = [])
    {
        $key = $params['key'] ?? null;
        $status = $params['status'] ?? null;
        $updatedDateFrom = $params['updated_date_from'] ?? null;
        $updatedDateTo = $params['updated_date_to'] ?? null;
        $size = $params['size'] ?? 10;
        $locale = $params['locale'] ?? 'en';

        $categories = PromotionCategory::withoutTrashed()
            ->with(['updatedBy:id,name'])
            ->when(!empty($key), function ($query) use ($key, $locale) {
                $query->where(function ($q) use ($key, $locale) {
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) LIKE ?", ["%{$key}%"])
                      ->orWhere('key', 'LIKE', "%{$key}%");
                });
            })
            ->when(!empty($status), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when(!empty($updatedDateFrom), function ($query) use ($updatedDateFrom) {
                $query->whereDate('updated_at', '>=', $updatedDateFrom);
            })
            ->when(!empty($updatedDateTo), function ($query) use ($updatedDateTo) {
                $query->whereDate('updated_at', '<=', $updatedDateTo);
            })
            ->orderBy('id')
            ->paginate($size);

        $categories->getCollection()->transform(function ($item) use ($locale) {
            return [
                'id' => $item['id'],
                'name' => json_decode($item['name']),
                'key' => $item['key'],
                'status' => $item['status'],
                'updated_by' => $item->updatedBy ? $item->updatedBy->name : 'System',
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ];
        });

        return $categories;
    }

    public function createCategory($params)
    {
        $category = new PromotionCategory();
        $category->name = is_string($params['name']) ? $params['name'] : json_encode($params['name']);
        $category->key = $params['key'] ?? Str::slug(json_decode($category->name, true)['en'] ?? '');
        $category->status = $params['status'];
        $category->updated_by = auth()->id();
        $category->save();

        return $category;
    }

    public function getCategory($category)
    {
        $category->load(['updatedBy:id,name']);
        $category->name = json_decode($category->name);
        $category->updated_by = $category->updatedBy ? $category->updatedBy->name : 'System';
        unset($category->deleted_at);
        unset($category->updatedBy);

        return $category;
    }

    public function updateCategory($params, $category)
    {
        $category->name = is_string($params['name']) ? $params['name'] : json_encode($params['name']);
        $category->key = $params['key'] ?? Str::slug(json_decode($category->name, true)['en'] ?? '');
        $category->status = $params['status'];
        $category->updated_by = auth()->id();
        $category->save();

        return $category;
    }

    public function deleteCategory($category)
    {
        if ($category->promotions()->count() > 0) {
            throw new \Exception('Cannot delete category that has promotions');
        }

        $category->delete();
        return true;
    }
}