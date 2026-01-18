@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px; font-size:13px;">
            <span>Dear </span>
            {{ $email }}
            <br />
        </p>

        <p style="margin-bottom:25px; font-size:13px;">
            Mapping order which has trade id is {{ $id }} cannot covered on Bitmex because of {{ $msg }}
            <br />
        </p>
    </div>
@endsection
