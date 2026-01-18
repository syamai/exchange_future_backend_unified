<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoticeRequest extends FormRequest
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
            'title' => 'required',
            'support_url' => 'required',
            'banner_url' => $this->getBannerRules(\Request('banner_url')),
            'banner_mobile_url' => $this->getBannerRules(\Request('banner_mobile_url')),
        ];
    }

    private function getBannerRules($input)
    {
        if (is_file($input)) {
            return 'required|image|mimes:jpg,jpeg,png|max:10240';
        }
        return 'required';
    }

    public function messages()
    {
        return [
            'banner_url.image' => 'validation.custom.banner_url.image',
            'banner_url.mimes' => 'validation.custom.banner_url.mimes'
        ];
    }
}
