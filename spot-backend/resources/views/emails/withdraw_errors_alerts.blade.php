@extends('emails.template')
@section('content')
    <div style="">

        <p style="margin-bottom:20px;">
            {!! __('emails.withdraw_errors_alerts.line_1', ['email' => $email], $locale) !!},
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_errors_alerts.line_2', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_errors_alerts.line_3', [], $locale): {{ $currency }}<br/>
            @lang('emails.withdraw_errors_alerts.line_4', [], $locale): {{ $amount }}<br/>
            @lang('emails.withdraw_errors_alerts.line_5', [], $locale): {{ $toAddress }}<br/>
            @lang('emails.withdraw_errors_alerts.line_6', [], $locale): {{ $date }}<br/>
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_errors_alerts.line_7', [], $locale)<br/>
            @lang('emails.withdraw_errors_alerts.line_8', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.withdraw_errors_alerts.line_9', [], $locale)
        </p>

    </div>
@endsection
