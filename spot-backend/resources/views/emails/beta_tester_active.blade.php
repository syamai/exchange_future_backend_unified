@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px;">
            {!! __('emails.beta_tester_active.dear_name', ['email' => $email], $locale) !!}
        </p>

        <p style="margin-bottom:10px;">
            {!! __('emails.beta_tester_active.line_1', ['email' => $adminEmail, 'pair' => $pair], $locale) !!}
        </p>

        <p style="margin-bottom:25px">
            {{--@lang('emails.team')--}}
        </p>
    </div>
@endsection
