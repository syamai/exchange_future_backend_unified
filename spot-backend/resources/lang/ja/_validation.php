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
    'accepted'        => ':attributeを承認してください。',
    'active_url'      => ':attributeは、有効なURLではありません。',
    'after'           => ':attributeには、:date以降の日付を指定してください。',
    'after_or_equal'  => ':attributeには、:date以降もしくは同日時を指定してください。',
    'alpha'           => ':attributeには、アルファベッドのみ使用できます。',
    'alpha_dash'      => ":attributeには、英数字('A-Z','a-z','0-9')とハイフンと下線('-','_')が使用できます。",
    'alpha_num'       => ":attributeには、英数字('A-Z','a-z','0-9')が使用できます。",
    'array'           => ':attributeには、配列を指定してください。',
    'before'          => ':attributeには、:date以前の日付を指定してください。',
    'before_or_equal' => ':attributeには、:date以前もしくは同日時を指定してください。',
    'between'         => [
        'numeric' => ':attributeには、:minから、:maxまでの数字を指定してください。',
        'file'    => ':attributeには、:min KBから:max KBまでのサイズのファイルを指定してください。',
        'string'  => ':attributeは、:min文字から:max文字にしてください。',
        'array'   => ':attributeの項目は、:min個から:max個にしてください。',
    ],
    'boolean'         => ":attributeには、'true'か'false'を指定してください。",
    'confirmed'       => ':attributeと:attribute確認が一致しません。',
    'date'            => ':attributeは、正しい日付ではありません。',
    'date_format'     => ":attributeの形式は、':format'と合いません。",
    'different'       => ':attributeと:otherには、異なるものを指定してください。',
    'digits'          => ':attributeは、:digits桁にしてください。',
    'digits_between'  => ':attributeは、:min桁から:max桁にしてください。',
    'dimensions'           => ':attributeは、正しい縦横比ではありません。',
    'distinct'             => ':attributeに重複した値があります。',
    'email'                => ':attributeは、有効なメールアドレス形式で指定してください。',
    'exists'               => '選択された:attributeは、有効ではありません。',
    'file'                 => ':attributeはファイルでなければいけません。',
    'filled'               => ':attributeは必須です。',
    'gt'                   => [
        'numeric' => ':attribute must be greater than :value.',
        'file'    => ':attribute must be greater than :value kilobytes.',
        'string'  => ':attribute must be greater than :value characters.',
        'array'   => ':attribute must have more than :value items.',
    ],
    'gte'                  => [
        'numeric' => ':attribute must be greater than or equal :value.',
        'file'    => ':attribute must be greater than or equal :value kilobytes.',
        'string'  => ':attribute must be greater than or equal :value characters.',
        'array'   => ':attribute must have :value items or more.',
    ],
    'image'                => ':attributeには、画像を指定してください。',
    'in'                   => '選択された:attributeは、有効ではありません。',
    'in_array'             => ':attributeは、:otherに存在しません。',
    'integer'              => ':attributeには、整数を指定してください。',
    'ip'                   => ':attributeには、有効なIPアドレスを指定してください。',
    'ipv4'                 => ':attributeはIPv4アドレスを指定してください。',
    'ipv6'                 => ':attributeはIPv6アドレスを指定してください。',
    'json'                 => ':attributeには、有効なJSON文字列を指定してください。',
    'lt'                   => [
        'numeric' => ':attribute must be less than :value.',
        'file'    => ':attribute must be less than :value kilobytes.',
        'string'  => ':attribute must be less than :value characters.',
        'array'   => ':attribute must have less than :value items.',
    ],
    'lte'                  => [
        'numeric' => ':attribute must be less than or equal :value.',
        'file'    => ':attribute must be less than or equal :value kilobytes.',
        'string'  => ':attribute must be less than or equal :value characters.',
        'array'   => ':attribute must not have more than :value items.',
    ],
    'max'                  => [
        'numeric' => ':attributeには、:max以下の数字を指定してください。',
        'file'    => ':attributeには、:max KB以下のファイルを指定してください。',
        'string'  => ':attributeは、:max文字以下にしてください。',
        'array'   => ':attributeの項目は、:max個以下にしてください。',
    ],
        'mimes'                => ':attributeには、:valuesタイプのファイルを指定してください。',
        'mimetypes'            => ':attributeには、:valuesタイプのファイルを指定してください。',
        'min'                  => [
            'numeric' => ':attributeには、:min以上の数字を指定してください。',
            'file'    => ':attributeには、:min KB以上のファイルを指定してください。',
            'string'  => ':attributeは、:min文字以上にしてください。',
            'array'   => ':attributeの項目は、:min個以上にしてください。',
        ],
        'not_in'               => '選択された:attributeは、有効ではありません。',
        'not_regex'            => ':attribute format is invalid.',
        'numeric'              => ':attributeには、数字を指定してください。',
        'present'              => ':attributeは、必ず存在しなくてはいけません。',
        'regex'                => ':attributeには、有効な正規表現を指定してください。',
        'required'             => ':attributeは、必ず指定してください。',
        'required_if'          => ':otherが:valueの場合、:attributeを指定してください。',
        'required_unless'      => ':otherが:values以外の場合、:attributeを指定してください。',
        'required_with'        => ':valuesが指定されている場合、:attributeも指定してください。',
        'required_with_all'    => ':valuesが全て指定されている場合、:attributeも指定してください。',
        'required_without'     => ':valuesが指定されていない場合、:attributeを指定してください。',
        'required_without_all' => ':valuesが全て指定されていない場合、:attributeを指定してください。',
        'same'                 => ':attributeと:otherが一致しません。',

    'size'                 => [
        'numeric' => ':attributeには、:sizeを指定してください。',
        'file'    => ':attributeには、:size KBのファイルを指定してください。',
        'string'  => ':attributeは、:size文字にしてください。',
        'array'   => ':attributeの項目は、:size個にしてください。',
    ],
    'string'               => ':attributeには、文字を指定してください。',
    'timezone'             => ':attributeには、有効なタイムゾーンを指定してください。',
    'unique'               => '指定の:attributeは既に使用されています。',
    'uploaded'             => ':attributeのアップロードに失敗しました。',
    'url'                  => ':attributeは、有効なURL形式で指定してください。',


    // Custom rule message
    'old_password'                              => ':attribute 間違っている',
    'no_space'                                  => ':attribute 空白を含めることはできません',
    'valid_ethereum_address'                    => ':attribute 有効なEthereumアドレスでなければならない',
    'valid_bitcoin_address'                     => ':attribute 有効なBitcoinアドレスでなければなりません',
    'valid_tetherus_address'                    => ':attribute 有効なTetherUSアドレスでなければなりません',
    'email_verify'                              => 'このサイトにログインするにはメールを確認してください',
    'verify_register_email'                     => ':attribute 登録されている',
    'verify_pending_email'                      => ':attribute 登録されています。 電子メールを取得しないでください？',
    'is_not_temp_email'                         => ':attribute 使い捨ての電子メールです',
    'send_verify_user'                          => ':attribute 無効です',
    'email_reset_password_exists'               => 'ごめんなさい。 ユーザーメールは存在しません',

    'is_invalid_otp_code'                       => 'OTPコードが無効です',
    'unique_currency_address'                   => 'アドレスはすでに取得済みです',
    'email_active'                              => 'あなたのアカウントはロックされています。',

    'verified_email'                            => 'Please verify your email to active account.',
    'unverified_email'                          => 'Please verify your email to active account.',
    'unregistered_email'                        => 'The :attribute is registered.',
    'password_white_space'                      => 'Password should not contain white space.',
    'correct_password'                          => 'Password didn\'t match.',
    'google_authentication_code_valid'          => 'Google認証番号が無効です',
    'is_invalid_google_authenticator_code'      => 'Google認証番号が無効です',
    'email_change_unregistered'                 => 'Email registered',
    'email_change_not_pending'                  => 'Email registered',
    'blockchain_address'                        => ':attributeが無効です。',
    'otp_not_used'                              => '次のコードが生成されるまでお待ちください。',
    'law_accepted'                              => 'You can not register if you select "No"',
    'is_contract_address'                       => 'このコントラクトアドレスは無効です。',
    'unregistered_token'                        => ':attribute は既に登録されています。',
    'unregistered_token_name'                   => ':attribute は既に登録されています。',
    'unregistered_coin_name'                    => ':attribute は既に登録されています。',
    'unregistered_contract_address'             => ':attributeペアの硬貨は既に存在しています。',

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
            'length' => 'Google認証番号は6桁です'
        ],
        'tel' => [
            'numeric' => '数字を入力してください'
        ],
        'zip_code' => [
            'numeric' => '数字を入力してください'
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
        'email' => 'Eメール',
        'user_id' => 'ユーザーID',
        'password' => 'パスワード',
        'repeat_password' => 'パスワードを再度入力してください',
        'agree' => '同意する',
        'google_authenticator_code' => 'Google認証システム',
        'recovery_code' => 'recovery code',
        'confirm_email' => 'confirm email',
        'blockchain_address' => 'ブロックチェーンアドレス',
        'transaction_explorer' => 'transaction explorer',
        'minium_quantity' => 'minium quantity',
        'minium_amount' => 'minimum amount',
        'minium_total' => 'minimum total',
        'total_limit' => '合計制限',
        'amount_limit' => '数量制限',
        'minium_withdrawal' => 'minium withdrawal',
        'zip_code' => '郵便番号',
        'state_region' => '都道府県',
        'building_room' => 'building room',
        'coin_name' => '通貨名',
        'contract_address' => 'コントラクトアドレス',
        'coin' => 'coin',
        'precision' => '最小入力可能桁数',
        'token_name' => 'トークン名',
        'token' => 'トークン',
        'icon' => 'アイコン',
        'time_reset' => '制限期間',
        'market_price' => '市場価格',
        'minimum_amount' => '最小指定数量',
        'minimum_total' => '最小指定金額',
        'minimum_withdrawal' => '最小出金額',
        'limit' => '出金限度額',
        'fee' => '取引手数料',
        'pair' => 'ペア',
        'name' => '名前',
        'tel'   => '電話番号',
        'address' => '住所',
        'city' => '市区町村',
        'send_confirmer' => '確認者',
        'tx_hash' => '取引ハッシュ'
    ],

];
