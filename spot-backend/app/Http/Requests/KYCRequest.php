<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\KYC;

class KYCRequest extends FormRequest
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
        $id = \Request('user_id');
        $rules = [
            'full_name'     => 'required|max:255',
            'id_number'     => 'required|max:255|unique:user_kyc,id_number,'.$id.',user_id',
            'gender'        => 'required|in:male,female',
            'country'       => 'required|max:255'
            //'otp'           => 'required|numeric|digits:6|otp_not_used|correct_otp',
        ];

        $existed = \Request('id') ? KYC::where('id', \Request('id'))->exists() : false;
        if (!$existed) {
            return array_merge($rules, [
                'id_front'  => 'image|mimes:jpg,jpeg,png|max:10240',
                'id_back'   => 'image|mimes:jpg,jpeg,png|max:10240',
                'id_selfie' => 'image|mimes:jpg,jpeg,png|max:10240',
            ]);
        }
        // Kyc is existed.
        return array_merge($rules, [
            'id_front'      => \Request('id_front') ? 'image|mimes:jpg,jpeg,png|max:10240' : '',
            'id_back'       => \Request('id_back') ? 'image|mimes:jpg,jpeg,png|max:10240' : '',
            'id_selfie'     => \Request('id_selfie') ? 'image|mimes:jpg,jpeg,png|max:10240' : '',
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'id_number.unique' => 'The passport/ID number has already been taken.'
        ];
    }
}
