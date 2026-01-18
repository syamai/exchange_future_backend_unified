<?php

namespace App\Http\Requests;

use App\Consts;
use App\Rules\CoinsConvertSmallBalanceRule;
use Illuminate\Foundation\Http\FormRequest;

class ConvertSmallBalanceRequest extends FormRequest
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
            'coins' => ['required', 'string', new CoinsConvertSmallBalanceRule],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
        ];
    }
}
