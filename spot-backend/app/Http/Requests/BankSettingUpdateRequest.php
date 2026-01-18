<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankSettingUpdateRequest extends FormRequest
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
            'bank_name' => 'required',
            'account_no' => 'required',
            'bank_branch' => 'required',
            'account_name' => 'required',
        ];
    }

    public function messages()
    {
        return [

        ];
    }
}
