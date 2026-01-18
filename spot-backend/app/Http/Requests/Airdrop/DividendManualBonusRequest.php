<?php

namespace App\Http\Requests\Airdrop;

use Illuminate\Foundation\Http\FormRequest;

class DividendManualBonusRequest extends FormRequest
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
            '*.user_id' => 'required',
            '*.total_volume' => 'required',
            '*.amount' => 'required',
            '*.wallet' => 'required',
            '*.email' => 'required|email',
            '*.coin' => 'required',
            '*.filter_from' => 'required',
            '*.filter_to' => 'required',
            '*.type' => 'required',
            '*.bonus_currency' => 'required'
        ];
    }
}
