@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.confirmation_reset_password.dear_account', ['email' => $email], $locale) !!},
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.confirmation_reset_password.receiving_text', [], $locale)
        </p>

        <p style="">
            @lang('emails.confirmation_reset_password.please_click', [], $locale)
        </p>

        <p style="margin-bottom:20px">
            <a style="color:#0064aa" href="{{reset_password_url($token)}}">{{reset_password_url($token)}}</a>
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.confirmation_reset_password.valid_24h', [], $locale)<br/>
            @lang('emails.confirmation_reset_password.please_confirm', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.confirmation_reset_password.check_confirm', [], $locale)
        </p>

        <p style="margin-bottom:20px;">
            @lang('emails.confirmation_reset_password.thank_you', [], $locale)
        </p>
    </div>
@endsection
