@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px;">
            {!! __('emails.vouchers.dear_name', ['email' => $email], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            {!! __('emails.vouchers.line_1', ['amount' => \App\Utils\BigNumber::new($user['amount'])->toString(), 'type' => $user['type'], 'currency' => strtoupper($user['currency'])], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            {!! __('emails.vouchers.line_2', ['days' => (int)$user['expires_date_number'] > 1 ? (int)$user['expires_date_number']. ' days' : (int)$user['expires_date_number']. ' day'], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.vouchers.thank_you', [], $locale)
        </p>
    </div>
@endsection
