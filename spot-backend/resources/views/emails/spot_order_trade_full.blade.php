@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.spot_order_trade_full.dear_name', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.spot_order_trade_full.line_1', ['trade_name' => $trade_name, 'pair' => $pair], $locale): {{ $price }} {{ $currency }}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.spot_order_trade_full.line_2', [], $locale): {{ $order_type }} <br />
            @lang('emails.spot_order_trade_full.line_3', [], $locale): {{ $quantity }} {{ $coin }}<br />
            @lang('emails.spot_order_trade_full.line_4', [], $locale): {{ $date }} (UTC)
        </p>

        <p style="">
            @lang('emails.spot_order_trade_full.line_5', [], $locale)
        </p>

        <p style="">
            @lang('emails.spot_order_trade_full.line_6', [], $locale)
        </p>

    </div>
@endsection
