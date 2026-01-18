<?php

namespace App\Http\Services;

class GeetestService
{
    const CLIENT_ID = 'web';

    public static function getSdk()
    {
        return new GeetestLib(config('geetest.id'), config('geetest.key'));
    }

    public static function preVerify()
    {
        $gtSdk = self::getSdk();
        $status = $gtSdk->pre_process(GeetestService::CLIENT_ID);
        $data = json_decode($gtSdk->get_response_str(), true);
        $data['gtserver'] = $status;
        return $data;
    }

    public static function secondaryVerify($data)
    {
        $data = collect($data);
        $gtSdk = self::getSdk();
        if ($data->get('gtserver', '') == 1) {
            $result = $gtSdk->success_validate(
                $data->get('geetest_challenge', ''),
                $data->get('geetest_validate', ''),
                $data->get('geetest_seccode', ''),
                GeetestService::CLIENT_ID
            );
        } else {
            $result = $gtSdk->fail_validate(
                $data->get('geetest_challenge', ''),
                $data->get('geetest_validate', ''),
                $data->get('geetest_seccode', '')
            );
        }
        return $result;
    }
}
