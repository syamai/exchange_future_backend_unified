<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\TransferRequest;
use App\Http\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Cache\RedisLock;
use Knuckles\Scribe\Attributes\QueryParam;

/**
 * @subgroup Transfer
 *
 * @authenticated
 */
class TransferController extends AppBaseController
{
    protected $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transfer",
     *     summary="[Private] Transfer balance",
     *     description="Transfer balance",
     *     tags={"Private API"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="from", description="from wallet main|spot|future", type="string", example="main"),
     *              @OA\Property(property="to", description="to wallet main|spot|future", type="string", example="future"),
     *              @OA\Property(property="symbol", description="crypto symbol", type="string", example="usdt"),
     *              @OA\Property(property="amount", description="crypto amount transfer", type="number", example="1000"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example=null),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */

    public function transfer(TransferRequest $request)
    {
        $lock = new RedisLock(Redis::client(), 'transfer_future_' . Auth::id(), 100);
        if ($lock->acquire()) {
            $res = $this->transferService->transfer($request);
            $lock->release();
            if ($res['status']) {
                return $this->sendResponse(true, $res['msg']);
            }
            return $this->sendError($res['msg']);
        } else {
            logger()->info('======== TRANSFER FUTURE LOCK ============');
        }
    }

    public function transferFuture(TransferRequest $request)
    {
        $res = $this->transferService->transferFuture($request->all());
        if ($res['status']) {
            return $this->sendResponse(true, $res['msg']);
        }
        return $this->sendError($res['msg']);
    }

    public function referralFuture()
    {
        return $this->sendError('This API is no longer supported.');

        // $validator = Validator::make(request()->all(), [
        //     'buyer_id' => 'string|required',
        //     'seller_id' => 'string|required',
        //     'symbol' => 'string|required',
        //     'asset' => 'string|required',
        //     'buy_fee' => 'numeric|required',
        //     'sell_fee' => 'numeric|required',
        // ]);
        // $validator->validate();
        // $request = request()->only([
        //     'buyer_id',
        //     'seller_id',
        //     'symbol',
        //     'asset',
        //     'buy_fee',
        //     'sell_fee',
        // ]);
        // \App\Jobs\CalculateReferralCommissionFuture::dispatchIfNeed($request);

        // return true;
    }

    /**
     * Transfer Deposit and Withdraw History
     *
     * Note:
     *      from main -> to spot : Deposit || from spot -> to main : Withdraw
     *
     * @return JsonResponse
     *
     * @response {
     * "data": {
     * "current_page": 1,
     * "data": [
     *  {
     *  "id": 82,
     *  "amount": "82",
     *  "coin": "btc",
     *  "created_at": 1571042782669,
     *   "updated_at": 1571042782669,
     *   "email": "hung.pham3+1111@sotatek.com",
     *   "destination": "spot",
     *   "source": "main"
     *   "user_id": "16"
     *  }
     * ],
     * "first_page_url": "http://localhost:8080/api/v1/transfer/history?page=1",
     * "from": 1,
     * "last_page": 1,
     * "last_page_url": "http://localhost:8080/api/v1/transfer/history?page=1",
     * "links": [
     * {
     * "url": null,
     * "label": "&laquo; Previous",
     * "active": false
     * },
     * {
     * "url": "http://localhost:8080/api/v1/transfer/history?page=1",
     * "label": "1",
     * "active": true
     * },
     * {
     * "url": null,
     * "label": "Next &raquo;",
     * "active": false
     * }
     * ],
     * "next_page_url": null,
     * "path": "http://localhost:8080/api/v1/transfer/history",
     * "per_page": 10,
     * "prev_page_url": null,
     * "to": 2,
     * "total": 2
     * },
     * "status": true
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated."
     * }
     */
    #[QueryParam("dateStart", "string", "Date Start. ", required: false, example: '2023-04-25')]
    #[QueryParam("page", "integer", "page. ", required: true, example: 1)]
    #[QueryParam("limit", "integer", "limit record. ", required: true, example: 10)]
    #[QueryParam("coin", "integer", "coin. ", required: false, example: "btc")]
    public function getTransferHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->sendResponse($this->transferService->getTransferHistory($request->all(), $user));
    }
}
