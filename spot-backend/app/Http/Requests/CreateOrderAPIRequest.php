<?php

namespace App\Http\Requests;

use App\Http\Requests\API\BaseRequest;

class CreateOrderAPIRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'trade_type' => 'required|in:buy,sell',
            'currency' => 'required',
            'coin' => 'required',
            'type' => 'required|in:limit,market,stop_limit,stop_market',
            'quantity' => 'required|numeric|min:0',
            'price' => 'required_if:type,limit,stop_limit|numeric|min:0',
            'base_price' => 'required_if:type,stop_limit,stop_market|nullable|numeric|min:0',
            'stop_condition' => 'required_if:type,stop_limit,stop_market|in:ge,le',
        ];
    }
}
