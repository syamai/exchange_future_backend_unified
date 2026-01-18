@extends('emails.template')
@section('content')
    <div>
        <p style="margin-bottom:25px;">
            {!! __('emails.register_denied.hello', ['email' => $email], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.register_denied.line_1', ['APP_NAME' => config('app.name')], $locale)
        </p>
        <p style="margin-bottom:25px;">
            @lang('emails.register_denied.line_2', [], $locale)<br/>
            @lang('emails.register_denied.line_3', [], $locale)
        </p>
        <p style="margin-bottom:25px;">
            @lang('emails.register_denied.line_4', [], $locale)
        </p>
        <p style="margin-bottom:25px;">
            @lang('emails.register_denied.line_5', ['contact_email' => $contactEmail], $locale)
        </p>
        <p style="margin-bottom:25px;">
            @lang('emails.register_denied.line_6', [], $locale)<br/>
            @lang('emails.register_denied.line_7', ['APP_NAME' => config('app.name')], $locale)
        </p>
    </div>
@endsection
