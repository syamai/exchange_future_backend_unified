<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AmlTransactionCreateRequest extends FormRequest
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
            'total_amount' => 'required',
            'payment' => 'required',
            'currency' => 'required',
            'amal_price' => 'required',
        ];
    }

    public function messages()
    {
        return [

        ];
    }
}
