@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:20px;">
            {!!  __('emails.change_password.dear_name', ['email' => $email], $locale) !!}
        </p>
        <p style="margin-bottom:20px;">
            @lang('emails.change_password.line_1', [], $locale) {{ $updatedAt }}(UTC)<br/>
        </p>

        <p style="">
            @lang('emails.change_password.line_2', [], $locale)
        </p>

    </div>
@endsection
