@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:10px">
            <span>@lang('emails.received_verify_document.dear_account', [], $locale) </span>
            {{ $email }},
        </p>

         <p style="margin-bottom:10px">
            @lang('emails.liquid_position_line_1', ['symbol' => $symbol, 'current_qty' => $current_qty], $locale)
        </p>

         <p style="margin-bottom:10px">
            @lang('emails.liquid_position_line_2', ['symbol' => $symbol, 'mark_price' => $mark_price, 'liquidation_price' => $liquidation_price], $locale)
        </p>

         <p style="margin-bottom:10px">
            @lang('emails.liquid_position_line_3', ['symbol' => $symbol], $locale)
        </p>

         <table>
            <thead>
                <tr>
                    <th style="width:100px"> @lang('emails.liquid_position.side', [], $locale)</th>
                    <th style="width:100px"> @lang('emails.liquid_position.qty', [], $locale)</th>
                    <th style="width:100px"> @lang('emails.liquid_position.lev', [], $locale)</th>
                    <th style="width:100px"> @lang('emails.liquid_position.mark_price', [], $locale)</th>
                    <th style="width:100px"> @lang('emails.liquid_position.liq_price', [], $locale)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align:center">{{$side}}</td>
                    <td style="text-align:center">{{$current_qty}}</td>
                    <td style="text-align:center">{{$leverage}}</td>
                    <td style="text-align:center">{{$mark_price}}</td>
                    <td style="text-align:center">{{$liquidation_price}}</td>
                </tr>
            </tbody>
        </table>

         <p style="margin-bottom:10px">
            @lang('emails.liquid_position_line_4', ['symbol' => $symbol, 'current_qty' => $current_qty, 'leverage' => $leverage], $locale)
        </p>

         <p style="margin-bottom:10px">
            @lang('emails.liquid_position_line_5', ['maint_margin' => $maint_margin, 'liquidation_price' => $liquidation_price, 'bankrupt_price' => $bankrupt_price], $locale)
        </p>

         <p style="margin-bottom:10px">
            @lang('emails.liquid_position_line_6', ['date' => $date, 'symbol' => $symbol, 'index_price' => $index_price, 'mark_price' => $mark_price], $locale)
        </p>
    </div>
@endsection
