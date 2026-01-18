<?php

namespace App\Http\Controllers;

use App\Http\Services\MasterdataService;
use Illuminate\Http\JsonResponse;

/**
 * @SWG\Swagger(
 *   basePath="/api/v1",
 *   @SWG\Info(
 *     title="Laravel Generator APIs",
 *     version="1.0.0",
 *   )
 * )
 * This class should be parent class for other API controllers
 * Class AppBaseController
 */
class AppBaseController extends Controller
{
    public function sendResponse($result, $message = null): JsonResponse
    {
        $res = [
            'success' => true,
            'message' => $message,
            'dataVersion' => MasterdataService::getDataVersion(),
            'data' => $result,
        ];
        return response()->json($res);
    }

    public function sendError($error, $code = 404): JsonResponse
    {
        $res = [
            'success' => false,
            'message' => $error,
        ];

        return response()->json($res, $code);
    }

    public function sendIsEmpty($msg, $code = 200): JsonResponse
    {
        $res = [
            'success' => false,
            'message' => $msg,
        ];

        return response()->json($res, $code);
    }
}
