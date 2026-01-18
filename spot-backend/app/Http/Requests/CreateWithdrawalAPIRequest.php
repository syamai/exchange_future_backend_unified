<?php

namespace App\Http\Requests;

use App\Models\UserWithdrawalAddress;
use App\Rules\MaxDecimalRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawalAPIRequest extends FormRequest
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
        logger('CreateWithdrawalAPIRequest');

        $validAddress = '|valid_currency_address:' . \Request('currency') . ',' . \Request('blockchain_address') . ',' . \Request('network_id');

        $rules = [
            'currency' => 'required|string|check_enable_deposit_withdrawal:withdrawal',
            'network_id' => 'required|integer|exists:networks,id',
            'blockchain_address' => 'required|string|max:256' . $validAddress,
            //'blockchain_sub_address' => 'required_if:currency,xrp|string|max:256',
            'amount' => ['numeric' , 'max:0', new MaxDecimalRule(request('currency'))]
        ];

        if ($this->user()->hasOTP()) {
            $rules['otp'] = 'required|otp_not_used|correct_otp';
        }
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'currency.check_enable_deposit_withdrawal' => __('funds.disable_coin_msg'),
        ];
    }
}
