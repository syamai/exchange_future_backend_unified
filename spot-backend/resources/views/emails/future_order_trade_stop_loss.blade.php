@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.future_order_trade_stop_loss.dear_name', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.future_order_trade_stop_loss.line_1', ['order_type' => $order_type, 'symbol' => $symbol], $locale): {{ $price }} {{ $currency }}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.future_order_trade_stop_loss.line_2', [], $locale) <br />
            @lang('emails.future_order_trade_stop_loss.line_3', [], $locale): {{ $quantity }} {{ $coin }}<br />
            @lang('emails.future_order_trade_stop_loss.line_4', [], $locale): {{ $date }} (UTC)
        </p>

        <p style="">
            @lang('emails.future_order_trade_stop_loss.line_5', [], $locale)
        </p>

        <p style="">
            @lang('emails.future_order_trade_stop_loss.line_6', [], $locale)
        </p>

    </div>
@endsection
