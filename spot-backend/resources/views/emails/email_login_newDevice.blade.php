@extends('emails.template')
@section('content')
    <p style="margin-bottom:10px">
        {!!  __('emails.login_new_device.dear_account', ['email' => $email], $user_locale ?? $locale) !!}
    </p>
    <p style="">
        @lang('emails.login_new_device.attemped_access', ['APP_NAME' => $appName], $user_locale ?? $locale)
    </p>


    <div style="">
        <div style="margin-top: 20px; margin-bottom: 20px; font-size: 14px;">
            <p>@lang('emails.login_new_device.email', [], $user_locale ?? $locale): {{ $email }}</p>
            <p>@lang('emails.login_new_device.device', [], $user_locale ?? $locale): {{ $browse }} ({{ $device }})</p>
            <p>@lang('emails.login_new_device.time', [], $user_locale ?? $locale): {{ $time }} </p>
            <p>@lang('emails.login_new_device.ip', [], $user_locale ?? $locale): {{ $ip_address }}</p>
        </div>
    </div>
    <p style="">@lang('emails.login_new_device.legitimate_activity', [], $user_locale ?? $locale)</p>
    <p style="margin-bottom:20px; word-wrap: break-word;">
        <a style="color:#0064aa" href="{{ grant_device_url($code) }}">{{ grant_device_url($code) }}</a>
    </p>
    <p style="">@lang('emails.login_new_device.change_password', [], $user_locale ?? $locale)</p>
    <p style="margin-bottom:20px; word-wrap: break-word;">
        <a style="color:#0064aa;text-decoration: underline;" href="{{ get_custom_email_url("/account/setting-password") }}">@lang('emails.login_new_device.label_change_password', [], $user_locale ?? $locale)</a>
    </p>
    <p style="margin-bottom:10px">
        @lang('emails.login_new_device.thank_you', [], $user_locale ?? $locale)
    </p>
@endsection
