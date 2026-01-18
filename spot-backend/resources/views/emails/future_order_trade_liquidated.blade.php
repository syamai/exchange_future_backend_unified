@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.future_order_trade_liquidated.dear_name', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.future_order_trade_liquidated.line_1', ['order_type' => $order_type, 'symbol' => $symbol], $locale)
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.future_order_trade_liquidated.line_2', [], $locale): {{ $position_type }}<br />
            @lang('emails.future_order_trade_liquidated.line_3', [], $locale): x{{ $leverage }}<br />
            @lang('emails.future_order_trade_liquidated.line_4', [], $locale): {{ $price }} {{ $currency }}<br />
            @lang('emails.future_order_trade_liquidated.line_5', [], $locale): {{ $date }} (UTC)
        </p>

        <p style="">
            @lang('emails.future_order_trade_liquidated.line_6', [], $locale)
        </p>

        <p style="">
            @lang('emails.future_order_trade_liquidated.line_7', [], $locale)
        </p>

    </div>
@endsection
