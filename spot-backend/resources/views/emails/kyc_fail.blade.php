@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.kyc_fail.dear_name', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.kyc_fail.line_1', [], $locale)
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.kyc_fail.line_2', [], $locale)<br />
            @lang('emails.kyc_fail.line_3', [], $locale)<br />
            @lang('emails.kyc_fail.line_4', [], $locale)<br />
            @lang('emails.kyc_fail.line_5', [], $locale)
        </p>

        <p style="">
            @lang('emails.kyc_fail.line_6', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{ get_custom_email_url("/account/dashboard") }}">@lang('emails.kyc_fail.line_7', [], $user_locale ?? $locale)</a>
        </p>

        <p style="">
            @lang('emails.kyc_fail.line_8', [], $locale)
        </p>
        <p style="">
            @lang('emails.kyc_fail.line_9', [], $locale)
        </p>

    </div>
@endsection
