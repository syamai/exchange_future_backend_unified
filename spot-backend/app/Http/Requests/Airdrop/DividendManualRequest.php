<?php

namespace App\Http\Requests\Airdrop;

use Illuminate\Foundation\Http\FormRequest;

class DividendManualRequest extends FormRequest
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
            'type' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|after_or_equal:start_date',
            'coin' => 'required',
            'volume' => 'required'
        ];
    }
}
