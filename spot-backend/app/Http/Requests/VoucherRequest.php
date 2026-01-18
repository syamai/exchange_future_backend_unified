<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\StatusVoucher;
use App\Enums\TypeVoucher;
use App\Models\Voucher;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class VoucherRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rule = 'unique:vouchers,name';
        if (!empty($this->id)) {
            $rule .= "," . $this->id. ",id,deleted_at,NULL";
        } else {
            $rule .= ",NULL,NULL,deleted_at,NULL";
        }

        return [
            'name' => [
                'required',
                'max:255',
                $rule
            ],
            'type' => ['required', Rule::in(array_column(TypeVoucher::cases(), 'value'))],
            'currency' => ['required', Rule::in(array_column(Currency::cases(), 'value'))],
            'amount' => 'required|numeric',
            'number' => 'nullable|numeric',
            'conditions_use' => 'required|numeric',
            'expires_date_number' => 'required|numeric',
        ];
    }
}
