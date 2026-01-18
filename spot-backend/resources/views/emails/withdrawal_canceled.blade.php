@extends('emails.template')
@section('content')
        <div>
            <p style="margin-bottom:20px;">
                {!! __('emails.ban_account.dear_name', ['email' => $email], $locale) !!}
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled.line_1', [], $locale)
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled.line_2', [], $locale): {{ strtoupper($transaction->currency) }}<br/>
                @lang('emails.withdrawal_canceled.line_3', [], $locale): {{ \App\Utils\BigNumber::new(abs($transaction->amount))->toString() }}<br/>
                @lang('emails.withdrawal_canceled.line_4', [], $locale): {{ $transaction->to_address }}<br/>
                @lang('emails.withdrawal_canceled.line_5', [], $locale): {{ $createdAt }}(UTC)<br/>
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled.line_6', [], $locale)<br/>
                @lang('emails.withdrawal_canceled.line_7', [], $locale)<br/>
                @lang('emails.withdrawal_canceled.line_8', [], $locale)<br/>
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled.line_9', [], $locale)
            </p>
        </div>
@endsection
