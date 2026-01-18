<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * @OA\Info(title="DEVELOPMENT GUIDE", version="1.0")
 * @OA\SecurityScheme(
 *     type="http",
 *     description="Login with email and password to get the authentication token",
 *     name="Token based Based",
 *     in="header",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="apiAuth",
 * )
 */

/**
 * @OA\Tag(
 *    name="Authentication",
 *    description="Authentication requests"
 *  ),
 * @OA\Tag(
 *     name="Account",
 *     description="Account requests"
 *   ),
 * @OA\Tag(
 *    name="Market",
 *    description="Market data requests"
 *  ),
 * @OA\Tag(
 *     name="Trading",
 *     description="Trading requests"
 *   ),
 */


/**
 * @OA\Delete (
 *     path="/api/v1/hmac-tokens/:hmac_token",
 *     tags={"Authentication"},
 *     summary="[Private] Log out of the session",
 *     description="Log out of the session",
 *     @OA\Parameter(
 *          name="hmac_token",
 *          in="path",
 *          required=true,
 *          @OA\Schema(type="string"),
 *          description="The ID of the HMAC token to delete"
 *      ),
*       @OA\Parameter(
 *           name="otp",
 *           in="query",
 *           required=true,
 *           @OA\Schema(type="string"),
 *           description="The ID of the HMAC token to delete"
 *       ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="null"),
 *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
 *              @OA\Property(property="data", type="string", example=1)
 *          )
 *      ),
 *      @OA\Response(
 *        response=500,
 *        description="Server error",
 *        @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=false),
 *              @OA\Property(property="message", type="string", example="Server Error"),
 *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
 *              @OA\Property(property="data", type="string", example=null)
 *          )
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthenticated",
 *          @OA\JsonContent(
 *              @OA\Property(property="message", type="string", example="Unauthenticated.")
 *          )
 *      ),
 *      security={{ "apiAuth": {} }}
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function getFilePath()
    {
        $now = Carbon::now()->format('Y-m-d');
        $basePath = storage_path('app/exports');
        $path = "{$basePath}/{$now}";
        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true, true);
        }
        return $path;
    }
}
