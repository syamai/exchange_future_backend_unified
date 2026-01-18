@extends('emails.template')
@section('content')
    <div>
        <p style="margin-bottom:25px;">
            {!! __('emails.register_completed.hello', ['email' => $email], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.register_completed.line_1', [], $locale)<br/>
            {!! __('emails.register_completed.line_2', ['APP_NAME' => config('app.name')], $locale) !!}
        </p>
        <p style="margin-bottom:25px;">
            <a style="color:#0064aa;text-decoration: underline;" href="{{ $linkLogin }}">{!! __('emails.register_completed.line_3', ['APP_NAME' => config('app.name')], $locale) !!}</a>
        </p>
        <p style="margin-bottom:25px;">
            {!! __('emails.register_completed.line_4', ['APP_NAME' => config('app.name')], $locale) !!}
        </p>
    </div>
@endsection
