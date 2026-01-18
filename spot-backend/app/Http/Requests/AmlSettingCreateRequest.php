<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AmlSettingCreateRequest extends FormRequest
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
            'amount' => 'required',
        'usd_price' => 'required',
        'eth_price' => 'required',
        'btc_price' => 'required',
        'usd_sold_amount' => 'required',
        'eth_sold_amount' => 'required',
        'btc_sold_amount' => 'required',
        'presenter_price' => 'required',
        'presentee_price' => 'required',

        ];
    }

    public function messages()
    {
        return [

        ];
    }
}
