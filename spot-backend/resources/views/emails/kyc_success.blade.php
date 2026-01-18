@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.kyc_success.dear_name', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.kyc_success.line_1', [], $locale)<br />
            @lang('emails.kyc_success.line_2', [], $locale)
        </p>

        <p style="">
            @lang('emails.kyc_success.line_3', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{ get_custom_email_url("/funds/deposits-wallet?coin=usdt") }}">@lang('emails.kyc_success.line_4', [], $user_locale ?? $locale)</a>
        </p>

        <p style="">
            @lang('emails.kyc_success.line_5', [], $locale)
        </p>

    </div>
@endsection
