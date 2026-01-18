<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateOrCreateWithdrawalAddressAPIRequest extends FormRequest
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
            'wallet_address' => 'required|is_withdrawal_address:' . \Request('coin') . ',' . \Request('tag'),
            'wallet_name' => 'required',
            'wallet_sub_address' => 'required_if:coin, "xrp"',
        ];
    }
}
