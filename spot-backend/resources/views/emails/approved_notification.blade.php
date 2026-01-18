@extends('emails.layouts.withdraw')

@section('content')
    <div style="margin-bottom: 20px">
        <p style="margin-bottom:20px; margin-top: 0">@lang('emails.approved_notification.name', [ 'name' => $name ] , $locale)</p>
        <p>@lang('emails.approved_notification.text11', [], $locale)</p>
        <p>@lang('emails.approved_notification.text12', [], $locale)</p>
        <p style="margin: 0">@lang('emails.approved_notification.text1', [], $locale)</p>
        <div style="margin-bottom: 35px;">
            <div style="font-size: 14px;">
                <ul style="padding-left: 17px">
                    <li>@lang('emails.approved_notification.text2', [], $locale) : {{ $currency }}</li>
                    <li>@lang('emails.approved_notification.text3', [], $locale) : {{ $amount }}</li>
                    <li>@lang('emails.approved_notification.text4', [], $locale) : {{ $withdrawAddress }}</li>
                    <li>@lang('emails.approved_notification.text5', [], $locale) : {{ $createdAt }} (UTC)</li>
                </ul>
            </div>
        </div>
        <p style="margin-bottom:0px; margin-top: 0">@lang('emails.approved_notification.text6', [], $locale)</p>
        <p style="margin-bottom:20px; margin-top: 0">@lang('emails.approved_notification.text7', [], $locale)</p>
        <p style="margin-bottom:20px; margin-top: 0">@lang('emails.approved_notification.text8', [], $locale)</p>
        <p style="margin-bottom:0px; margin-top: 0">@lang('emails.approved_notification.text9', [], $locale)</p>
        <p style="margin-bottom:0px; margin-top: 0">@lang('emails.approved_notification.text10', [], $locale)</p>
    </div>
@endsection
