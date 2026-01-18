@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px;">
            {!! __('emails.register_beta_tester.dear_name', [], $locale) !!}
        </p>

        <p style="margin-bottom:10px;">
            @lang('emails.register_beta_tester.line_1', [], $locale)
        </p>

        <p style="margin-bottom:10px;">
            @lang('emails.register_beta_tester.email', [], $locale)
            {{ $email }}
        </p>

        <p style="margin-bottom:25px">
            @lang('emails.team')
        </p>
    </div>
@endsection
