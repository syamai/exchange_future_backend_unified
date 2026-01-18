@extends('emails.template')
@section('content')
    <div style="">
        <p style="margin-bottom:25px;">
            {!! __('emails.register_completed.hello', ['email' => $email], $locale) !!}
        </p>

        @if(count($changedAddress) > 0)
            <p style="margin-bottom:25px;">
                @lang('emails.change_coldWallet_setting.line_1', [], $locale) <br/>
                @lang('emails.change_coldWallet_setting.line_2', [], $locale) <br/>

                @foreach ($changedAddress as $item)
                {!! __('emails.change_coldWallet_setting.line_3', ['coin' => $item['coin'], 'oldAddress' => $item['oldAddress'], 'newAddress' => $item['newAddress']], $locale) !!} <br/>
                @endforeach
            </p>
            <p style="margin-bottom:25px;">
                @foreach ($changedEmail as $item)
                {!! __('emails.change_coldWallet_setting.line_4', ['oldEmail' => $item['oldEmail'], 'newEmail' => $item['newEmail']], $locale) !!}
                @endforeach
            </p>
        @else
            <p style="margin-bottom:25px;">
                @lang('emails.change_coldWallet_setting.line_1', [], $locale) <br/>
                @lang('emails.change_coldWallet_setting.line_2', [], $locale) <br/>
                @foreach ($changedEmail as $item)
                {!! __('emails.change_coldWallet_setting.line_4', ['oldEmail' => $item['oldEmail'], 'newEmail' => $item['newEmail']], $locale) !!}
                @endforeach
            </p>
        @endif
    </div>
@endsection
