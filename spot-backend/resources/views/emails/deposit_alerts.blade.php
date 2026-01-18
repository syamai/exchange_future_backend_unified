@extends('emails.template')
@section('content')
    <div style="">

        <p style="margin-bottom:20px;">
            {!! __('emails.deposit_alerts.line_1', ['email' => $email], $locale) !!},
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.deposit_alerts.line_2', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.deposit_alerts.line_3', [], $locale): {{ $amount }} {{ $coin }}<br/>
            @lang('emails.deposit_alerts.line_4', [], $locale): {{ $date }} (UTC)<br/>
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.deposit_alerts.line_5', [], $locale)
        </p>
        <p style="margin-bottom:25px;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{ get_custom_email_url("/funds/history-wallet") }}">@lang('emails.deposit_alerts.line_6', [], $user_locale ?? $locale)</a>
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.deposit_alerts.line_7', ['APP_NAME' => config('app.name')], $locale)
        </p>

    </div>
@endsection
