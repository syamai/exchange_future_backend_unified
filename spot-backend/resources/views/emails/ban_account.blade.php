@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px;">
            {!! __('emails.ban_account.dear_name', ['email' => $email], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.ban_account.line_1', [], $locale)<br/>
            @lang('emails.ban_account.line_2', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.ban_account.line_3', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            <a href="{{ $contactLink }}">{{ $contactLink }}</a>
        </p>
        <p style="margin-bottom:25px;">
            @lang('emails.registed.thank_you', [], $locale)
        </p>
    </div>
@endsection
