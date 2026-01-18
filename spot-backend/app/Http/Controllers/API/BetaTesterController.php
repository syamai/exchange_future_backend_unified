<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\BetaTesterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BetaTesterController extends AppBaseController
{
    private BetaTesterService $service;

    public function __construct(BetaTesterService $service)
    {
        $this->service = $service;
    }

    public function registerBetaTester(Request $request): JsonResponse
    {
        $inputs = $request->all();
        $validator = Validator::make($inputs, [
            'coin' => 'required|string',
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->messages()->first());
        }

        try {
            $result = $this->service->register($request, $inputs);
            return $this->sendResponse($result, 'ok');
        } catch (\Exception $e) {
            logger($e);
            return $this->sendError($e->getMessage());
        }
    }
}
