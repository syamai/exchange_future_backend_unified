<?php

namespace Snapshot\Http\Requests;

use Snapshot\Rules\CurrencyValidRule;
use Snapshot\Rules\TakeProfitRule;
use Illuminate\Foundation\Http\FormRequest;

class TakeProfitCreateRequest extends FormRequest
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
            'amount' => ['required', new TakeProfitRule($this->currency)],
            'currency' => ['required', new CurrencyValidRule]
        ];
    }
}
