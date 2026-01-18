<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterNewErc20 extends FormRequest
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
        $regexPrecision = '/^0\.0*1{0,1}0*$/';

        return [
            'coin_setting.symbol' => [
                'required', 'alpha_num',
                Rule::unique('coins', 'coin')->where(function ($query) {
                    return $query->whereRaw('LOWER(coin) != ? ', [ strtolower($this->input('symbol')) ])
                        ->where('env', config('blockchain.network'));
                })
            ],
            'coin_setting.name' => 'required',
            'coin_setting.network' => 'nullable|string',
            'coin_setting.decimals' => 'required|numeric',
            'coin_setting.image_base64' => 'required|string',
            'coin_setting.required_confirmations' => 'required|numeric',
            'coin_setting.contract_address' => [
                'required',
                'valid_contract_address',
                Rule::unique('coins', 'contract_address')->where(function ($query) {
                    return $query->where('env', config('blockchain.network'));
                })
            ],
            'trading_setting.*.coin' => [
                'required',
//                'same:coin_setting.symbol',
                'alpha_num',
                Rule::unique('coins', 'coin')->where(function ($query) {
                    return $query->whereRaw('LOWER(coin) != ? ', [ strtolower($this->input('symbol')) ])
                        ->where('env', config('blockchain.network'));
                }),
                Rule::unique('coin_settings', 'coin')->where(function ($query) {
                    return $query->whereRaw('LOWER(coin) != ? ', [ strtolower($this->input('symbol')) ]);
                })
            ],
            'trading_setting.*.currency' => [
                'required',
                Rule::exists('coin_settings', 'currency'),
            ],
//            'trading_setting.*.sell_limit' => 'required|numeric',
            'trading_setting.*.market_price' => 'required|numeric',
//            'trading_setting.*.buy_limit' => 'required|numeric',
//            'trading_setting.*.days' => 'required|numeric',
            'trading_setting.*.minimum_quantity' => 'required|numeric',
            'trading_setting.*.quantity_precision' => 'required|numeric|regex:' . $regexPrecision,
            'trading_setting.*.price_precision' => 'required|numeric|regex:' . $regexPrecision,
            'trading_setting.*.minimum_amount' => 'required|numeric',
            'trading_setting.*.taker_fee' => 'required|numeric',
            'trading_setting.*.maker_fee' => 'required|numeric',
            'withdrawal_setting.currency' => 'required|same:coin_setting.symbol',
//            'withdrawal_setting.limit' => 'required|numeric',
            'withdrawal_setting.fee' => 'required|numeric',
            'withdrawal_setting.minium_withdrawal1' => 'required|numeric',
            'withdrawal_setting.minium_withdrawal2' => 'required|numeric',
            'withdrawal_setting.minium_withdrawal3' => 'required|numeric',
            'withdrawal_setting.minium_withdrawal4' => 'required|numeric',
//            'withdrawal_setting.days' => 'required|numeric',
        ];
    }
}
