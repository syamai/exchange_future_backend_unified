<?php

namespace App\Http\Controllers\API;

use App\Consts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\PriceService;
use App\Http\Services\TransactionService;
use App\Http\Requests\CreateUsdDepositAPIRequest;
use App\Http\Requests\CreateFiatWithdrawalAPIRequest;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Utils;
use Knuckles\Scribe\Attributes\QueryParam;

/**
 * @subgroup Transactions
 *
 * @authenticated
 */
class TransactionAPIController extends AppBaseController
{
    private $transactionService;
    private TransactionRepository $transactionRepository;
    private $priceService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
        $this->transactionRepository = new TransactionRepository();
        $this->priceService = new PriceService();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions/{currency}",
     *     summary="[Private] Transaction History",
     *     description="Get transaction histories",
     *     tags={"Account"},
     *     @OA\Parameter(
     *         description="Currency name, If null API will get all transactions",
     *         in="path",
     *         name="currency",
     *         @OA\Schema(
     *             type="string",
     *             example="btc"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Get balances success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="null"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="fcf932880bcb19ea4b0d3b1bae533d5e2e5ae244"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                @OA\Property(property="current_page", type="integer", example=1),
     *                @OA\Property(
     *                    property="data",
     *                    type="array",
     *                    example={
     *                        {
     *                            "id": 82,
     *                            "blockchain_address": null,
     *                            "created_at": 1571042782669,
     *                            "currency": "eth",
     *                            "transaction_id": "7bab9fe80cbe6b9ac1c150f53698d4b60886def1",
     *                            "blockchain_sub_address": null,
     *                            "from_address": "0x3929F5083Afd20588443e2F3051473Ca552606F6",
     *                            "fee": "0.0010000000",
     *                            "status": "pending",
     *                            "tx_hash": null,
     *                            "transaction_date": "2019-10-14",
     *                            "updated_at": 1571042782669,
     *                            "to_address": "0xFd21C68DBDF483A298e58A604e64C9cb314984b8",
     *                            "user_id": 2,
     *                            "is_external": 1,
     *                            "amount": "0.9990000000"
     *                        }
     *                    },
     *                    @OA\Items(
     *                        @OA\Property(property="id", type="integer", example=82),
     *                    ),
     *                ),
     *                @OA\Property(property="first_page_url", type="string", example="/api/transactions?page=1"),
     *                @OA\Property(property="from", type="integer", example=1),
     *                @OA\Property(property="last_page", type="integer", example=1),
     *                @OA\Property(property="last_page_url", type="string", example="/api/transactions?page=1"),
     *                @OA\Property(property="next_page_url", type="string", example=null),
     *                @OA\Property(property="path", type="string", example="/api/transactions"),
     *                @OA\Property(property="per_page", type="integer", example=10),
     *                @OA\Property(property="prev_page_url", type="string", example=null),
     *                @OA\Property(property="to", type="integer", example=1),
     *                @OA\Property(property="total", type="integer", example=1),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="419",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
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

    /**
     * Transaction Deposit Withdraw History
     *
     * @return JsonResponse
     *
     * @response {
     * "data": {
     * "current_page": 1,
     * "data": [
     *  {
     *  "id": 82,
     *  "blockchain_address": null,
     *  "created_at": 1571042782669,
     *   "currency": "eth",
     *   "transaction_id": "7bab9fe80cbe6b9ac1c150f53698d4b60886def1",
     *   "blockchain_sub_address": null,
     *   "from_address": "0x3929F5083Afd20588443e2F3051473Ca552606F6",
     *   "fee": "0.0010000000",
     *   "status": "pending",
     *   "tx_hash": null,
     *   "transaction_date": "2019-10-14",
     *   "updated_at": 1571042782669,
     *   "to_address": "0xFd21C68DBDF483A298e58A604e64C9cb314984b8",
     *   "user_id": 2,
     *   "is_external": 1,
     *   "amount": "0.9990000000"
     *  }
     * ],
     * "first_page_url": "http://localhost:8080/api/v1/transactions/bnb?page=1",
     * "from": 1,
     * "last_page": 1,
     * "last_page_url": "http://localhost:8080/api/v1/transactions/bnb?page=1",
     * "links": [
     * {
     * "url": null,
     * "label": "&laquo; Previous",
     * "active": false
     * },
     * {
     * "url": "http://localhost:8080/api/v1/transactions/bnb?page=1",
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
     * "path": "http://localhost:8080/api/v1/orders/pending",
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
    #[QueryParam("currency", "string", "currency. ", required: false, example: 'bnb')]
    #[QueryParam("page", "integer", "page. ", required: true, example: 1)]
    #[QueryParam("limit", "integer", "limit record. ", required: true, example: 10)]
    #[QueryParam("type", "string", "Type deposit or withdraw. ", required: true, example: "deposit")]
    public function getHistory(Request $request, $currency = null)
    {
        $params = $request->all();
        $params['currency'] = $currency;

        $limit = $request->input('limit', Consts::DEFAULT_PER_PAGE);

        $data = $this->transactionService->getHistory($params, $limit);
        return $this->sendResponse($data);
    }

    public function withdrawUsd(CreateFiatWithdrawalAPIRequest $request)
    {
        DB::beginTransaction();
        try {
            $transaction = $this->transactionService->withdrawUsd($request->all());
            DB::commit();
            return $this->sendResponse($transaction);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getUsdWithdrawDaily(Request $request)
    {
        $data = $this->transactionService->getUsdWithdrawDaily();

        return $this->sendResponse($data);
    }

    public function getWithdrawDaily(Request $request)
    {
        $user = $request->user();
        $currency = $request->input('currency');

        $data = $this->transactionService->getWithdrawDaily($currency, $user->id);

        return $this->sendResponse($data);
    }

    public function depositUsd(CreateUsdDepositAPIRequest $request)
    {
        $user = $request->user();
        $input = $request->all();
        $input['user_id'] = $user->id;
        $input['status'] = Consts::TRANSACTION_STATUS_PENDING;
        $input['created_at'] = Utils::currentMilliseconds();
        $input['updated_at'] = Utils::currentMilliseconds();

        DB::beginTransaction();
        try {
            $transaction = $this->transactionService->depositUsd($input);
            DB::commit();
            return $this->sendResponse($transaction);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getUserTransactions(Request $request)
    {
        try {
            $balances = $this->transactionService->getUserTransactions($request->all());
            $prices = $this->priceService->getPricesAt($request->input('start'));
            $data = [
                'balances' => $balances,
                'startPrices' => $prices
            ];
            return $this->sendResponse($data);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function cancelUsdDepositTransaction(Request $request, $transactionId)
    {
        DB::beginTransaction();
        try {
            $data = $this->transactionService->cancelUsdDepositTransaction($transactionId);
            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getUsdTransactionHistory(Request $request)
    {
        $data = $this->transactionService->getUsdTransactionHistory($request->all());

        return $this->sendResponse($data);
    }

    public function getFee(Request $request)
    {
        $data = $this->transactionService->getFee($request->all());
        return $this->sendResponse($data);
    }

    public function getTotalFee(Request $request)
    {
        $data = $this->transactionService->getTotalFee($request->all());
        return $this->sendResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions/withdraw/total-pending-withdraw",
     *     summary="[Private] Get Total Pending Withdraw",
     *     description="Get total pending withdraw amount",
     *     tags={"Account"},
     *     @OA\Response(
     *         response=200,
     *         description="Get balances success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="null"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="array",
     *                example={
     *                    {
     *                        "total": "1.0000000000",
     *                        "currency": "eth"
     *                    },
     *                    {
     *                        "total": "1.0000000000",
     *                        "currency": "btc"
     *                    }
     *                },
     *                @OA\Items(
     *                    @OA\Property(property="total", type="string", example="1.0000000000"),
     *                    @OA\Property(property="currency", type="string", example="eth")
     *                ),
     *                @OA\Items(
     *                    @OA\Property(property="total", type="string", example="1.0000000000"),
     *                    @OA\Property(property="currency", type="string", example="btc")
     *                ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="419",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
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
    public function getTotalPendingWithdraw(Request $request)
    {
        $data = $this->transactionService->getTotalPendingWithdraw($request->all());
        return $this->sendResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions/withdraw/total-usd-pending-withdraw",
     *     summary="[Private] Get Total USD Pending Withdraw",
     *     description="Get total USD pending withdraw",
     *     tags={"Account"},
     *     @OA\Response(
     *         response=200,
     *         description="Get data success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="null"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={"total": "1001.8000000000"},
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="419",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
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
    public function getTotalUsdPendingWithdraw()
    {
        $data = $this->transactionService->getTotalUsdPendingWithdraw();
        return $this->sendResponse($data);
    }
}
