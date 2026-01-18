@extends('emails.template')
@section('content')
    <div style="">

        <p style="margin-bottom:10px">
            @lang('emails.hello', [], $locale)
            <strong>{{ $email }} </strong>
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.unlock_airdrop_fail.text1', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.unlock_airdrop_fail.text2', [], $locale)
            <strong>{{ $amount }} </strong>
            @lang('emails.unlock_airdrop_fail.text3', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.thankyou', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.team', [], $locale)
        </p>
    </div>
@endsection
