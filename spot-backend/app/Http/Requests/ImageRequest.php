<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageRequest extends FormRequest
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
            'file' => $this->getImageRules(\Request('file')),
        ];
    }

    private function getImageRules($input)
    {
        if (is_file($input)) {
            return 'required|image|mimes:jpg,jpeg,png|max:10240';
        }
        return 'required';
    }

    public function messages()
    {
        return [
            'file.image' => 'validation.custom.image.image',
            'file.mimes' => 'validation.custom.image.mimes'
        ];
    }
}
