<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DelRecoveryCodeWithAuthRequest extends FormRequest
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
            'otp_recovery_code' => 'required|verify_otp_recovery_code_with_auth',
        ];
    }

    public function attributes()
    {
        return [
            'otp_recovery_code' => 'validation.attributes.otp_recovery_code',
        ];
    }
}
