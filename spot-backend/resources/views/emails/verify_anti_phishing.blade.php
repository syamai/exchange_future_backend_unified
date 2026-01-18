@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px;">
            {!! __('emails.anti_phishing.dear_name', ['email' => $email], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            {!! $type == 'create' ? __('emails.anti_phishing.line_1_create', [], $locale) : __('emails.anti_phishing.line_1_change', [], $locale) !!}
        </p>

        <p>
            {!! __('emails.anti_phishing.line_2', [], $locale) !!}</br>
        </p>
        <p style="margin-bottom:25px; word-wrap: break-word;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{ grant_anti_phishing_url($code, $type) }}">@lang('emails.anti_phishing.hyperlink', [], $locale)</a>
        </p>
        <p>
            {!! __('emails.anti_phishing.line_3', [], $locale) !!}</br>
        </p>
        <p style="margin-bottom:25px; word-wrap: break-word;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{ get_custom_email_url("/account/security") }}">@lang('emails.anti_phishing.line_4', [], $locale)</a>
        </p>
    </div>
@endsection
