@extends('emails.template')
@section('content')
    <div>
        <p style="margin-bottom:25px;">
            {!! __('emails.memory_alert.hello', ['email' => $email], $locale) !!}
        </p>

        <p style="margin-bottom:25px;">
            {!! __('emails.memory_alert.line_1', ['threshold' => $threshold], $locale) !!}<br/>
            <ul>
                <li>{!! __('emails.memory_alert.memory_usage', ['memory_usage' => $data['memory_usage']], $locale) !!}</li>
                <li>{!! __('emails.memory_alert.memory_peak', ['memory_peak' => $data['memory_peak']], $locale) !!}</li>
                <li>{!! __('emails.memory_alert.timestamp', ['timestamp' => $data['timestamp']], $locale) !!}</li>
            </ul>
            {!! __('emails.memory_alert.line_2', [], $locale) !!}
        </p>
    </div>
@endsection
