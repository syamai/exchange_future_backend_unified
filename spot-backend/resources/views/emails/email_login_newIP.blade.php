@extends('emails.template')
@section('content')
    <div style="">

        <p style="margin-bottom:10px">@lang('Warning',[],$locale) {{ $username }}.</p>
        <p style="margin-bottom:10px">@lang('The system has detected that your account is logged in from an unused IP address.',[],$locale)</p>
        <p style="margin-bottom:10px">@lang('If this activity is not your own operation, please disable that device and contact us immediately',[],$locale)</p>
        <p style="margin-bottom:20px">Email: {{ $email }}</p>
        <div style="background-color: #f2f2f2; height: 130px; border: 1px solid #a3a3a3; margin-bottom: 20px;">
            <div style="float: left;">
                <img src="{{url('/images/device.png')}}" style="height: 80px; margin-top: 27px; margin-left: 60px;">
            </div>
            <div style="margin-top: 25px; font-size: 16px;">
                <p style="margin-left: 35%" >@lang('Device',[],$locale) : {{ $browse }} ({{ $device }})</p>
                <p style="margin-left: 35%">@lang('Time',[],$locale) : {{ $time }} (UTC)</p>
                <p style="margin-left: 35%">@lang('IP Address',[],$locale) : {{ $ip_address }}</p>
            </div>
        </div>
        <p style="margin-bottom:5px">{{ __(env('APP_NAME').' Team',[],$locale) }}</p>
        <p style="margin-bottom:25px">@lang('Automated message, please do not reply.',[],$locale)</p>
    </div>
@endsection
