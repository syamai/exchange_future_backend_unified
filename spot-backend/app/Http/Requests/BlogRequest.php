<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'cat_id' => 'required|integer|exists:blog_categories,id',
            'static_url' => [
				'required',
				'string',
				'max:200',
				'regex:/^[a-z0-9-]+$/',
				Rule::unique('blogs', 'static_url')->ignore($this->route('id')),
			],
            'thumbnail_url' => $this->getThumbnailRules(\Request('thumbnail_url')),
            'title_en' => 'required|string|max:200',
            'seo_title_en' => 'required|string|max:200',
            'meta_keywords_en' => 'required|string',
            'seo_description_en' => 'required|string|max:2000',
            'content_en' => 'required|string',
            'title_vi' => 'required|string|max:200',
            'seo_title_vi' => 'required|string|max:200',
            'meta_keywords_vi' => 'required|string',
            'seo_description_vi' => 'required|string|max:2000',
            'content_vi' => 'required|string',
            'status' => 'required|in:posted,hidden',
        ];
    }

    private function getThumbnailRules($input)
    {
        if (is_file($input)) {
            return 'required|image|mimes:jpg,jpeg,png|max:10240';
        }
        return 'required';
    }

    public function messages()
    {
        return [
            'thumbnail_url.image' => 'validation.custom.banner_url.image',
            'thumbnail_url.mimes' => 'validation.custom.banner_url.mimes'
        ];
    }
}
