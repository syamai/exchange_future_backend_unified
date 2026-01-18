<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ChangeAddressWhiteList extends FormRequest
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
        $active = \Request('active');
        return [
            'active' => 'required|boolean',
            'otp' => Request::user()->securitySetting->otp_verified && $active ? 'required|numeric|digits:6|otp_not_used|correct_otp' : '',
        ];
    }
}
