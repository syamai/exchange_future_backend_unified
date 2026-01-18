@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px; font-size:13px;">
            <span>@lang('emails.margin_deleverage.dear', [], $userLocale)</span>
            {{ $email }}
            <br />
        </p>

        <p style="margin-bottom:25px; font-size:13px;">
            @lang('emails.margin_deleverage.body1', [], $userLocale)
            <br />
        </p>

        <p style="margin-bottom:25px; font-size:13px;">
            @lang('emails.margin_deleverage.body2', [], $userLocale)
            <br />
        </p>

        <p style="margin-bottom:25px; font-size:13px;">
            <a style="color:#0064aa" href="{{ $marginExchangeUrl }}">{{ $marginExchangeUrl }}</a>
        </p>
    </div>
@endsection
