@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px;">
            @lang('emails.confirm_kyc.dear_name', [], $locale) <strong>{{ $email }}</strong>,
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.confirm_kyc.line_1', [], $locale)<br/>
            @lang('emails.confirm_kyc.line_2', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.confirm_kyc.line_3', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            <a href="{{ $link_login }}">{{ $link_login }}</a>
        </p>

        <p style="margin-bottom:25px;">
            * @lang('emails.confirm_kyc.line_4', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            * @lang('emails.confirm_kyc.line_5', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.confirm_kyc.line_6', [], $locale)
        </p>

        <p style="margin-bottom:25px;">
            ・ @lang('emails.confirm_kyc.line_7', [], $locale)<br/>
            ・ @lang('emails.confirm_kyc.line_8', [], $locale)<br/>
            ・ @lang('emails.confirm_kyc.line_9', [], $locale)<br/>
            ・ @lang('emails.confirm_kyc.line_10', [], $locale)<br/>
            ・ @lang('emails.confirm_kyc.line_11', [], $locale)<br/>
            ・ @lang('emails.confirm_kyc.line_12', [], $locale)<br/>
            ・ @lang('emails.confirm_kyc.line_13', [], $locale)<br/>
        </p>

        <p style="margin-bottom:25px;">
            @lang('emails.confirm_kyc.line_14', [], $locale)
        </p>
    </div>
@endsection
