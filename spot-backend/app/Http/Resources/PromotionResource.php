<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $locale = $request->get('locale', 'en');

        return [
            'id' => $this->id,
            'subject' => json_decode($this->subject),
            'content' => json_decode($this->content),
            'thumbnail' => $this->thumbnail,
            'thumbnail_full_url' => $this->thumbnail_full_url,
            'categories' => $this->categories->map(function ($category) use ($locale) {
                return [
                    'id' => $category->id,
                    'name' => json_decode($category->name),
                    'key' => $category->key,
                    'status' => $category->status
                ];
            }),
            'status' => $this->status,
            'isPinned' => $this->isPinned,
            'pinnedPosition' => $this->pinnedPosition,
            'url' => $this->url,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
