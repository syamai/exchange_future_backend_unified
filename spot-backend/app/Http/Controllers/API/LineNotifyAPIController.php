<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\LineNotifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineNotifyAPIController extends AppBaseController
{
    private $lineNotifyService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->lineNotifyService = new LineNotifyService();
    }

    /**
     * @param Request $request
     */
    public function getAuthCode(Request $request)
    {
        $goal = "web";
        $this->lineNotifyService->getAccessToken($request->all(), $goal);
        $callback = $this->lineNotifyService->getCallBackUri($request->all());
        return redirect()->away(config('app.url') . $callback);
    }

    public function getAuthCodeForMobile(Request $request)
    {
        $goal = "mobile";
        $this->lineNotifyService->getAccessToken($request->all(), $goal);
        $callback = $this->lineNotifyService->getCallBackUri($request->all());
        return redirect()->away($callback);
    }

    public function encryptId(Request $request)
    {
        return $result = $this->lineNotifyService->encryptId($request->all());
    }
}
