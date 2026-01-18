@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:5px">{{ __('emails.deposit_usd_alerts.text1', [], $locale) }}  <strong>{{$user_email}}</strong></p>
        <p style="margin-bottom:5px">{{ __('emails.deposit_usd_alerts.text2', [], $locale) }}</p>
        <p style="margin-bottom:5px">{{ __('emails.deposit_usd_alerts.code', [], $locale) }}: {{$transaction->code}}</p>
        <p style="margin-bottom:5px">{{ __('emails.deposit_usd_alerts.amount', [], $locale) }}: {{number_format(abs($transaction->amount))}} USD</p>
        <p style="margin-bottom:5px">{{ __('emails.deposit_usd_alerts.bank_name', [], $locale) }}: {{$transaction->bank_name}}</p>
        <p style="margin-bottom:5px">{{ __('emails.deposit_usd_alerts.bank_branch', [], $locale) }}: {{$transaction->bank_branch}}</p>
        <p style="margin-bottom:5px">{{ __('emails.deposit_usd_alerts.account_name', [], $locale) }}: {{$transaction->account_name}}</p>
        <p style="margin-bottom:25px;">{{ __('emails.deposit_usd_alerts.account_no', [], $locale) }}: {{$transaction->account_no}}</p>
    </div>
@endsection
