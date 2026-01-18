@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.withdraw_verify.line_1', ['email' => $email], $locale) !!},
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_verify.line_2', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_verify.line_4', [], $locale): {{ $amount }} {{ $currency }}<br/>
            @lang('emails.withdraw_verify.line_5', [], $locale): {{ $toAddress }}<br/>
            @lang('emails.withdraw_verify.line_6', [], $locale): {{ $date }} (UTC)<br/>
        </p>

        <p>
            @lang('emails.withdraw_verify.line_7', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{$url}}">@lang('emails.withdraw_verify.line_9', [], $user_locale ?? $locale)</a>
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_verify.line_10', ['APP_NAME' => config('app.name')], $locale)
        </p>
    </div>
@endsection
