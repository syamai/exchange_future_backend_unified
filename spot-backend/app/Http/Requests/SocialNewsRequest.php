<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialNewsRequest extends FormRequest
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
            'link_page' => 'required|url',
            'title_en' => 'required|string|max:200',
            'content_en' => 'required|string',
            'title_vi' => 'required|string|max:200',
            'content_vi' => 'required|string',
            'status' => 'required|in:posted,hidden',
            'thumbnail_url' => $this->getThumbnailRules(\Request('thumbnail_url')),
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
