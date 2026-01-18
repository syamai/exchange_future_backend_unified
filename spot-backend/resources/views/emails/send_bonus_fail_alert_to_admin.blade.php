@extends('emails.template')
@section('content')
    <div style="">

        <p style="margin-bottom:10px">
            @lang('emails.send_bonus_fail_alert.text1')
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.send_bonus_fail_alert.text2')
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.send_bonus_fail_alert.email'):
            <strong> {{$email}} </strong>
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.send_bonus_fail_alert.amount'):
            <strong> {{$amount}} {{strtoupper($currency)}}</strong>
        </p>

        <p style="margin-bottom:10px">
            Wallet:
            <strong> {{$wallet}} Balance</strong>
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.team')
        </p>
    </div>
@endsection
