<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ChangeMypagePasswordRequest extends FormRequest
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
            'password' => 'required|correct_password',
            'new_password' => 'required|string|min:8|max:72|different:password|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/|password_white_space',
            'new_password_confirm' => 'required|same:new_password',
            'otp' => Request::user()->securitySetting->otp_verified ? 'required|numeric|digits:6|otp_not_used|correct_otp' : '',
        ];
    }
}
