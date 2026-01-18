<?php

namespace App\Http\Requests;

use App\Consts;
use Illuminate\Foundation\Http\FormRequest;

class TransferBalanceRequest extends FormRequest
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
        $restrict = implode(',', [
            Consts::TYPE_MAIN_BALANCE,
            Consts::TYPE_EXCHANGE_BALANCE,
            Consts::TYPE_MARGIN_BALANCE,
            Consts::TYPE_AIRDROP_BALANCE,
        ]);

        $regexPercision = '/^-?[0-9]+(?:\.[0-9]{0,8})?$/';
        return [
            'coin_value' => 'required|numeric|regex:'.$regexPercision,
            'coin_type' => 'required',
            'from' => 'required|in:' . $restrict,
            'to' => 'required|in:' . $restrict,
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
            'coin_value.regex' => 'Amount percision is invalid.'
        ];
    }
}
