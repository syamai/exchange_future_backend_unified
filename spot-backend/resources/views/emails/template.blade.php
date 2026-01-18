@if (isset($userLocale))
    <html lang="{{ $userLocale }}">
    @else
        <html lang="{{ app()->getLocale() }}">
        @endif

        <head>
            <link href="https://fonts.googleapis.com/css?family=Inter" rel="stylesheet"/>
            <style>
                p {
                    margin: 0;
                }

                .im {
                    color: #333333 !important;
                }
            </style>
        </head>

        <body
            style=" color: #333333; background-color: #f2f2f2; font-family: 'Inter'; display: flex; justify-content: center; align-content: center; font-size: 14px;">
        <div style="max-width: 854px; background-color: #fff; margin: 0 auto; margin-bottom: 30px">
            @if (isset($banner))
                <div style="">
                    <img src="{{ $banner->banner }}" style="max-width:250px; margin: 10px;">
                </div>
            @endif
            <div style="padding: 35px 45px; line-height: 1.5">
                @yield('content')
                {{-- @if (isset($user) && (int) $user['is_anti_phishing'])
                            <p style="margin-bottom:25px; font-size:13px;">
                                @lang('emails.anti_phishing.title'): <strong>{{ $user['anti_phishing_code'] }}</strong>
                            </p>
                        @endif
                        <hr style="margin-bottom:30px;">
                        <p style="line-height:20px">
                            <strong class="font-size:13px;">{{ config('app.name') }}</strong> - <span
                                class="font-size:13px;">@lang('emails.footer.the_best_cryptocurrency')</span></p>
                        <p style="line-height:20px;margin-bottom:20px">
                            <a href="{{ config('app.web') }}" style="color:black; font-size:13px;">{{ config('app.web') }}</a>
                        </p>

                        <p style="color: #858585; line-height:20px;font-size:13px;">{{ __('(CO) '.env('APP_NAME').' - Address', [], $userLocale) }}</p>
                        <p style="color: #858585; line-height:20px;font-size:13px;">@lang('emails.footer.service_center', [], $userLocale)
                            / email.
                            <a :href="mailto:{{ @$setting['contact_email'] }}"
                               target="_blank">{{ @$setting['contact_email'] }}</a> / tel. {{ @$setting['contact_phone'] }} </p>
                        <p style="color: #858585; line-height:20px;font-size:13px">{{ @$setting['copyright'] }}</p>  --}}
                @if (isset($anti_phishing_code))
                    <div style="display: flex; padding-top: 20px">
                        <div
                            style="background-color: #00DDB3;padding: 2px 10px;border-bottom-left-radius: 5px;border-top-left-radius: 5px">
                            <b>{!! __('emails.anti_phishing') !!}</b></div>
                        <div
                            style="background-color: #dedede;padding: 2px 10px;border-top-right-radius: 5px;border-bottom-right-radius: 5px">
                            <b>{{ $anti_phishing_code['anti_phishing_code'] }}</b></div>
                    </div>
                @endif
                <div style="margin-top: 30px">
                    <p>--------------------------------------------</p>
                    <p>{{__('email.footer.line_1', [], $user_locale ?? ($locale ?? "en"))}}</p>
                    <p>{{__('email.footer.line_2_1', [], $user_locale ?? ($locale ?? "en"))}}</p>
                    <p><img src="{{ $banner->banner }}" style="max-width:75px; margin: 5px;"></p>
                    <p>{{__('email.footer.line_2_2', [], $user_locale ?? ($locale ?? "en"))}}</p>
                    <p>🌐 <a href="{{ env('WEB_URL') }}">{{ env('WEB_URL') }}</a></p>
                    <p>📧 <a href="mailto:{{ @$setting['contact_email'] }}" target="_blank">{{ @$setting['contact_email'] }}</a>
                        @if(@$setting['contact_phone'])
                            / ☎ {{ @$setting['contact_phone'] }}
                        @endif
                    </p>
                    <p>{{__('email.footer.line_3', [], $user_locale ?? ($locale ?? "en"))}}</p>
                    <p>{{__('email.footer.line_4', [], $user_locale ?? ($locale ?? "en"))}}</p>
                </div>
            </div>
            <div style="display:flex; justify-content: center; align-content: center;">
                @if(isset($footer))
                    <img src="{{ $footer->footer }}"
                         style="max-width:250px; margin: 0 auto; margin-bottom: 30px">
                @endif
        </div>
    </div>

</body>
