@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:10px">
            <span>@lang('emails.received_verify_document.dear_account', [], $locale) </span>
            {{ $email }}
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.received_verify_document.line_1', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.received_verify_document.line_2', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.received_verify_document.line_3', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.received_verify_document.line_4', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            *@lang('emails.received_verify_document.line_5', [], $locale)
        </p>

        <p style="margin-bottom:10px">
            @lang('emails.received_verify_document.line_6', [], $locale)
        </p>

        <p style="margin-bottom:40px">
            *@lang('emails.received_verify_document.line_7', [], $locale)
        </p>
    </div>
@endsection
