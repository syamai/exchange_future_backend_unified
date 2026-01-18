<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class AddressManagerAPIRequest extends FormRequest
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
            'coin' => 'required|string',
            'network_id' => 'required|integer|exists:networks,id',
            'name' => 'required|string|max:20',
            'wallet_address' => ['required', 'string', 'user_id_coin_wallet_xpr_eos:wallet_sub_address,coin', 'max:256'],
            //'wallet_sub_address' => 'nullable|string|max:256',
            'white_list' => 'required|boolean',
            'otp' => Request::user()->securitySetting->otp_verified ? 'required|numeric|digits:6|otp_not_used|correct_otp' : '',
        ];
    }
}
