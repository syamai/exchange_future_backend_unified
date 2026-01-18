@extends('emails.template')
@section('content')
    <div style="">

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_alerts.line_1', ['email' => $email], $locale),
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_alerts.line_2', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_alerts.line_4', [], $locale): {{ $amount }} {{ $coin }}<br/>
            @lang('emails.withdraw_alerts.line_5', [], $locale): {{ $toAddress }}<br/>
            @lang('emails.withdraw_alerts.line_6', [], $locale): {{ date('Y-m-d H:m:s', $date/1000) }} (UTC)<br/>
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_alerts.line_8', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{ get_custom_email_url("/funds/history-wallet?type=withdraw") }}">@lang('emails.withdraw_alerts.line_7', [], $locale)</a>
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_alerts.line_11', [], $locale)
        </p>
    </div>

@endsection
