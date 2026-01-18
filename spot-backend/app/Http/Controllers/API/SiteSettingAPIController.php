<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Services\IpStackClientService;
use App\Http\Services\SiteSettingService;
use App\Models\BlockCountry;
use App\Utils;
use App\Http\Controllers\AppBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteSettingAPIController extends AppBaseController
{
    public function index(Request $request)
    {
        $result = [];
        $settings = DB::table('settings')->where('key', '!=', Consts::SETTING_MIN_BLOCKCHAIN_ADDRESS_COUNT)->get();
        foreach ($settings as $item) {
            $result[$item->key] = $item->value;
        }
        return $this->sendResponse($result);
    }

    public function getClientIp(Request $request)
    {
        $clientUtil = Utils::getClientIp($request);
        $clientRequest = $request->ip();

        $client = array(
            "api-key-check" => $clientUtil,
            "request-ip" => $clientRequest,
            'HTTP_X_FORWARDED_FOR' => getenv('HTTP_X_FORWARDED_FOR'),
            'X-Forwarded-For' => getenv('X-Forwarded-For'),
            'CF-Connecting-IP' => getenv('CF-Connecting-IP'),
            'HTTP_CLIENT_IP' => getenv('HTTP_CLIENT_IP'),
            'HTTP_X_FORWARDED' => getenv('HTTP_X_FORWARDED'),
            'HTTP_FORWARDED_FOR' => getenv('HTTP_FORWARDED_FOR'),
            'HTTP_FORWARDED' => getenv('HTTP_FORWARDED'),
            'REMOTE_ADDR' => getenv('REMOTE_ADDR')
        );

        return $this->sendResponse($client);
    }

    public function getLocationInfo(Request $request)
    {
        $clientRequest = $request->ip();
        $ipStackClientService = app(IpStackClientService::class);

        $client = $ipStackClientService->getInfo($clientRequest);
        if ($client) {
            $blockCountry = BlockCountry::where('country_code', $client['country_code'])->first();
            $client['is_block'] = $blockCountry ? 1 : 0;
        }

//
//        $client = array(
//            "request-ip" => $clientRequest,
//        );

        return $this->sendResponse($client);
    }

    public function getImageMail(): JsonResponse
    {
        $data = app(SiteSettingService::class)->getImageMail();

        return $this->sendResponse($data);
    }
}
