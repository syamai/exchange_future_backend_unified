<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */
    'precision'            => 'The :attribute must have the precision of :precision',
    'accepted'             => 'The :attribute must be accepted.',
    'active_url'           => 'The :attribute is not a valid URL.',
    'after'                => 'The :attribute must be a date after :date.',
    'after_or_equal'       => 'The :attribute must be a date after or equal to :date.',
    'alpha'                => 'The :attribute may only contain letters.',
    'alpha_dash'           => 'The :attribute may only contain letters, numbers, dashes and underscores.',
    'alpha_num'            => 'The :attribute may only contain letters and numbers.',
    'array'                => 'The :attribute must be an array.',
    'before'               => 'The :attribute must be a date before :date.',
    'before_or_equal'      => 'The :attribute must be a date before or equal to :date.',
    'between'              => [
        'numeric' => 'The :attribute must be between :min and :max.',
        'file'    => 'The :attribute must be between :min and :max kilobytes.',
        'string'  => 'The :attribute must be between :min and :max characters.',
        'array'   => 'The :attribute must have between :min and :max items.',
    ],
    'boolean'              => 'The :attribute field must be true or false.',
    'confirmed'            => 'The :attribute confirmation does not match.',
    'date'                 => 'The :attribute is not a valid date.',
    'date_format'          => 'The :attribute does not match the format :format.',
    'different'            => 'The :attribute and :other must be different.',
    'digits'               => 'The :attribute must be :digits digits.',
    'digits_between'       => 'The :attribute must be between :min and :max digits.',
    'dimensions'           => 'The :attribute has invalid image dimensions.',
    'distinct'             => 'The :attribute field has a duplicate value.',
    'email'                => 'The :attribute field must be a valid email.',
    'exists'               => 'The selected :attribute is invalid.',
    'file'                 => 'The :attribute must be a file.',
    'filled'               => 'The :attribute field must have a value.',
    'gt'                   => [
        'numeric' => 'The :attribute must be greater than :value.',
        'file'    => 'The :attribute must be greater than :value kilobytes.',
        'string'  => 'The :attribute must be greater than :value characters.',
        'array'   => 'The :attribute must have more than :value items.',
    ],
    'gte'                  => [
        'numeric' => 'The :attribute must be greater than or equal :value.',
        'file'    => 'The :attribute must be greater than or equal :value kilobytes.',
        'string'  => 'The :attribute must be greater than or equal :value characters.',
        'array'   => 'The :attribute must have :value items or more.',
    ],
    'image'                => 'The :attribute must be an image.',
    'in'                   => 'The selected :attribute is invalid.',
    'in_array'             => 'The :attribute field does not exist in :other.',
    'integer'              => 'The :attribute must be an integer.',
    'ip'                   => 'The :attribute must be a valid IP address.',
    'ipv4'                 => 'The :attribute must be a valid IPv4 address.',
    'ipv6'                 => 'The :attribute must be a valid IPv6 address.',
    'json'                 => 'The :attribute must be a valid JSON string.',
    'lt'                   => [
        'numeric' => 'The :attribute must be less than :value.',
        'file'    => 'The :attribute must be less than :value kilobytes.',
        'string'  => 'The :attribute must be less than :value characters.',
        'array'   => 'The :attribute must have less than :value items.',
    ],
    'lte'                  => [
        'numeric' => 'The :attribute must be less than or equal :value.',
        'file'    => 'The :attribute must be less than or equal :value kilobytes.',
        'string'  => 'The :attribute must be less than or equal :value characters.',
        'array'   => 'The :attribute must not have more than :value items.',
    ],
    'max'                  => [
        'numeric' => 'The :attribute may not be greater than :max.',
        'file'    => 'The :attribute may not be greater than :max kilobytes.',
        'string'  => 'The :attribute may not be greater than :max characters.',
        'array'   => 'The :attribute may not have more than :max items.',
    ],
    'mimes'                => 'The :attribute must be a file of type: :values.',
    'mimetypes'            => 'The :attribute must be a file of type: :values.',
    'min'                  => [
        'numeric' => 'The :attribute must be at least :min.',
        'file'    => 'The :attribute must be at least :min kilobytes.',
        'string'  => 'The :attribute must be at least :min characters.',
        'array'   => 'The :attribute must have at least :min items.',
    ],
    'not_in'               => 'The selected :attribute is invalid.',
    'not_regex'            => 'The :attribute format is invalid.',
    'numeric'              => 'The :attribute must be a number.',
    'present'              => 'The :attribute field must be present.',
    'regex'                => 'The :attribute format is invalid.',
    'required'             => 'The :attribute field is required.',
    'required_if'          => 'The :attribute field is required when :other is :value.',
    'required_unless'      => 'The :attribute field is required unless :other is in :values.',
    'required_with'        => 'The :attribute field is required when :values is present.',
    'required_with_all'    => 'The :attribute field is required when :values is present.',
    'required_without'     => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same'                 => 'The :attribute and :other must match.',
    'size'                 => [
        'numeric' => 'The :attribute must be :size.',
        'file'    => 'The :attribute must be :size kilobytes.',
        'string'  => 'The :attribute must be :size characters.',
        'array'   => 'The :attribute must contain :size items.',
    ],
    'string'               => 'The :attribute must be a string.',
    'timezone'             => 'The :attribute must be a valid zone.',
    'unique'               => 'The :attribute has already been taken.',
    'uploaded'             => 'The :attribute failed to upload.',
    'url'                  => 'The :attribute format is invalid.',


    // Custom rule message
    'old_password'                              => 'The :attribute is incorrect.',
    'no_space'                                  => 'The :attribute must not contain whitespace.',
    'valid_ethereum_address'                    => 'The :attribute must be a valid Ethereum address.',
    'valid_bitcoin_address'                     => 'The :attribute must be a valid Bitcoin address.',
    'valid_tetherus_address'                    => 'The :attribute must be a valid TetherUS address.',
    'verified_email'                            => 'Please verify your email to active account.',
    'unverified_email'                          => 'Please verify your email to active account.',
    'unregistered_email'                        => 'The :attribute is registered.',
    'is_not_temp_email'                         => 'The :attribute is a disposable email',
    'send_verify_user'                          => 'The :attribute is invalid.',
    'email_reset_password_exists'               => 'The inputted e-mail address does not exist',

    'voucher_existed'                           => 'Sorry. The code does not exist',
    'voucher_is_expired'                        => 'Sorry. The code is expired',
    'unique_voucher'                            => 'Sorry. The code is used',
    'is_invalid_otp_code'                       => 'The OTP code is invalid',
    'unique_metadata_bounty'                    => 'The link has already been taken.',
    'unique_currency_address'                   => 'The address has already been taken.',
    'email_active'                              => 'Your account has been locked.',
    'password_white_space'                      => 'Password should not contain whitespace.',
    'correct_password'                          => 'Password didn\'t match.',
    'google_authentication_code_valid'          => 'Google authentication code is invalid',
    'is_invalid_google_authenticator_code'      => 'Google authentication code is invalid',
    'email_change_unregistered'                 => 'Email registered',
    'email_change_not_pending'                  => 'Email registered',
    'blockchain_address'                        => 'The :attribute is invalid.',
    'law_accepted'                              => 'You can not register if you select "No"',
    'otp_not_used'                              => 'Please wait for next verification code to generate.',
    'is_contract_address'                       => 'The contract address is invalid.',
    'unregistered_token'                        => 'The token has already been taken.',
    'unregistered_token_name'                   => 'The token name has already been taken.',
    'unregistered_coin_name'                    => 'The name has already been existed.',
    'unregistered_contract_address'             => 'The contract address has already been existed.',
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'referral_id' => [
            'exists' => 'The referral code is invalid',
        ],
        'base_price' => [
            'min' => 'Stop Price must be at least :min',
            'precision' => 'Stop Price must have the precision of :precision',
        ],
        'quantity' => [
            'min' => 'Amount must be at least :min',
            'precision' => 'Amount must have the precision of :precision',
        ],
        'total' => [
            'min' => 'Total must be at least :min',
            'precision' => 'Total must have the precision of :precision',
        ],
        'price' => [
            'min' => 'Price must be at least :min',
            'precision' => 'Price must have the precision of :precision',
        ],
        'google_authenticator_code' => [
            'length' => 'The google authentication code length must be 6'
        ],
        'tel' => [
            'numeric' => 'Please enter a number'
        ],
        'zip_code' => [
            'numeric' => 'Please enter a number'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [
        'email' => 'email',
        'user_id' => 'user ID',
        'password' => 'password',
        'repeat_password' => 'repeat password',
        'new_password' => 'new password',
        'confirm_new_password' => 'confirm new password',
        'agree' => 'agree',
        'google_authenticator_code' => 'google authenticator code',
        'recovery_code' => 'recovery code',
        'confirm_email' => 'confirm email',
        'blockchain_address' => 'blockchain address',
        'transaction_explorer' => 'transaction explorer',
        'minium_quantity' => 'minimum quantity',
        'minium_amount' => 'minimum amount',
        'minium_total' => 'minimum total',
        'total_limit' => 'total limit',
        'amount_limit' => 'amount limit',
        'minium_withdrawal' => 'minimum withdrawal',
        'zip_code' => 'zipcode',
        'state_region' => 'state/region',
        'building_room' => 'building room',
        'coin_name' => 'name',
        'token_name' => 'token name',
        'precision' => 'precision',
        'contract_address' => 'contract address',
        'coin' => 'coin',
        'token' => 'token',
        'icon' => 'icon',
        'time_reset' => 'time reset',
        'market_price' => 'market price',
        'minimum_amount' => 'minimum amount',
        'minimum_total' => 'minimum total',
        'minimum_withdrawal' => 'minimum withdrawal',
        'limit' => 'limit',
        'fee' => 'fee',
        'pair' => 'pair',
        'name' => 'name',
        'send_confirmer' => 'confirmer',
        'tx_hash' => 'transaction hash'
    ],
];
