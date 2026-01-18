@extends('emails.template')
@section('content')
        <div>
            <p style="margin-bottom:20px;">
                {!! __('emails.withdrawal_canceled_new.dear_name', ['email' => $email], $locale) !!}
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled_new.line_1', [], $locale)
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled_new.line_2', [], $locale): {{ \App\Utils\BigNumber::new(abs($transaction->amount))->toString() }} {{ strtoupper($transaction->currency) }}<br/>
                @lang('emails.withdraw_verify.line_6', [], $locale): {{ $createdAt }}(UTC)<br/>
                @lang('emails.withdrawal_canceled_new.line_3', [], $locale)<br/>
                @lang('emails.withdrawal_canceled_new.line_4', [], $locale)<br/>
                @lang('emails.withdrawal_canceled_new.line_5', [], $locale)<br/>
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled_new.line_6', [], $locale)<br/>
                @lang('emails.withdrawal_canceled_new.line_7', [], $locale)<br/>
            </p>
    
            <p style="margin-bottom:20px;">
                @lang('emails.withdrawal_canceled_new.line_8', [], $locale)
                @lang('emails.withdrawal_canceled_new.line_9', [], $locale)
            </p>
        </div>
@endsection
