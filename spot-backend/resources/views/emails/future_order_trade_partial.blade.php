@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.future_order_trade_partial.dear_name', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.future_order_trade_partial.line_1', ['order_type' => $order_type, 'symbol' => $symbol], $locale): {{ $price }} {{ $currency }}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.future_order_trade_partial.line_2', [], $locale): {{ $side }} <br />
            @lang('emails.future_order_trade_partial.line_3', [], $locale): {{ $quantity }} {{ $coin }}<br />
            @lang('emails.future_order_trade_partial.line_4', [], $locale): {{ $remaining_quantity }} {{ $coin }}<br />
            @lang('emails.future_order_trade_partial.line_5', [], $locale): {{ $date }} (UTC)
        </p>

        <p style="">
            @lang('emails.future_order_trade_partial.line_6', [], $locale)
        </p>

        <p style="">
            @lang('emails.future_order_trade_partial.line_7', [], $locale)
        </p>

    </div>
@endsection
