<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFiatWithdrawalAPIRequest extends FormRequest
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
        $rules = [
            'amount' => 'numeric|max:0',
            // 'bank_name' => 'required',
            // 'bank_branch' => 'required',
            // 'account_name' => 'required',
            // 'account_no' => 'required',
        ];

        if ($this->user()->hasOTP()) {
            $rules['otp'] = 'required|otp_not_used|correct_otp';
        }

        return $rules;
    }

    /**
     * Modifying input before validation
     * @return array
     */
//    public function prepareForValidation()
//    {
//        // All inputs from the user
//        $input = $this->all();
//        logger($input);
//        // Modify or Add new array key/values
//        $input['bank_name']     = 'National Investment Bank (NI Bank)';
//        $input['bank_branch']   = 'Mongolia';
//        $input['account_name']  = '';
//        $input['account_no']    = '';
//
//        $this->replace($input);
//    }
}
