<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\SettingService;
use App\Http\Services\EnableTradingSettingService;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Client;

/**
 * @group  Account
 */
class SettingAPIController extends AppBaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    private SettingService $SettingService;

    public function __construct(SettingService $SettingService)
    {
        $this->SettingService = $SettingService;
    }

    public function index(): JsonResponse
    {
        $res = $this->SettingService->getremainamlsetting();
        return $this->sendResponse($res);
    }

    /**
     * Update the specified resource in storage.
     *
     */
    public function update()
    {
        return $this->SettingService->saveremainamlsetting();
    }

    public function getPairCoinSetting(Request $request): JsonResponse
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'coin' => 'required|string',
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        $enableTradingSettingService = new EnableTradingSettingService();
        $data = $enableTradingSettingService->getPairCoinSetting($inputs);
        return $this->sendResponse($data);
    }

    /**
     * Get client secret
     *
     * @return JsonResponse
     *
     * @response {
     * "success": true,
     * "message": null,
     * "dataVersion": "5d9b75e3b804d14e55e52c723f30f0eb3d94acce",
     * "data": {
     * "client_secret": "nFzLk7YjUzI2Qorhb43etp7ZSZYMdJ1PiJJUtWbN",
     * "client_id": 1
     * }
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     */
    public function getClients(): JsonResponse
    {
        $clientSecret = env('CLIENT_SECRET', null);
        if ($clientSecret) {
            $clientID = Client::query()->where('secret', $clientSecret)->value('id') ?? null;
        }
        return $this->sendResponse([
            'client_secret' => $clientSecret,
            'client_id' => $clientID
        ]);
    }
}
