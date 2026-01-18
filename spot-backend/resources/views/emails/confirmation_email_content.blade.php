@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.registed.confirmation_email.hello', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            {!!  __('emails.welcome', ['APP_NAME' => $appName], $locale) !!}
        </p>

        <p style="">
            @lang('emails.registed.click_on_link', [], $locale)
        </p>

        <a style="color:#0064aa" href="{{ confirm_email_url($code) }}">{{ confirm_email_url($code) }}</a>

        <p style="margin-top:20px;">
            @lang('emails.registed.valid_24h', [], $locale)<br />
            @lang('emails.registed.please_complete', [], $locale)
        </p>

        <p style="margin-top:20px;">
            @lang('emails.registed.thank_you', [], $locale)
        </p>
    </div>
@endsection
