@extends('emails.template')
@section('content')
    <div style="">

        <p style="margin-bottom:10px">
            @lang('emails.unlock_airdrop_fail_alert.text1', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.unlock_airdrop_fail_alert.text2', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.unlock_airdrop_fail_alert.email', [], $locale):
            <strong> {{$email}} </strong>
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.unlock_airdrop_fail_alert.amount', [], $locale):
            <strong> {{$amount}} </strong>
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.team')
        </p>
    </div>
@endsection
