<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Enums\StatusVoucher;
use App\Enums\TypeVoucher;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\OrderService;
use App\Http\Services\VoucherService;
use App\Models\Leaderboard;
use App\Models\User;
use App\Models\UserTradeVolumePerDay;
use App\Models\Voucher;
use App\Utils\BigNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoucherAPIController extends AppBaseController
{
    protected $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/voucher/get-list-voucher",
     *     summary="[Public] Get list voucher",
     *     description="Get list voucher",
     *     tags={"Private API"},
     *     @OA\Parameter(
     *         description="status",
     *         in="query",
     *         name="status",
     *         @OA\Schema(
     *             type="string",
     *             example="available"
     *         )
     *     ),
     *    @OA\Parameter(
     *         description="type",
     *         in="query",
     *         name="type",
     *         @OA\Schema(
     *             type="string",
     *             example="future"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Limit",
     *         in="query",
     *         name="limit",
     *         @OA\Schema(
     *             type="string",
     *             example="10"
     *         )
     *     ),
     *      @OA\Parameter(
     *         description="expires_date",
     *         in="query",
     *         name="expires_date",
     *         @OA\Schema(
     *             type="string",
     *             example="ASC"
     *         )
     *     ),
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
     *                example="fcf932880bcb19ea4b0d3b1bae533d5e2e5ae244"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={
     *                      "id": 1,
     *                     "name": "voucher",
     *                      "type": "future",
     *                      "amount": "12121212.0000000000",
     *                      "status": "available",
     *                      "expires_date": "2022-11-29",
     *                      "number": "12.0000000000",
     *                      "conditions_use": "0.0000000000",
     *                      "expires_date_number": "0.0000000000",
     *                      "created_at": "2022-11-29 14:13:30",
     *                      "updated_at": "2022-11-29 14:13:33",
     *                      "deleted_at": null
     *                }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Not Found.",
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

    public function getListVoucher(Request $request): JsonResponse
    {
        try {
            return $this->sendResponse([
                'data' => $this->voucherService->getListVoucher($request),
            ]);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function totalSpot(Request $request)
    {
        $request->type = TypeVoucher::SPOT->value;
        return $this->voucherService->getListVoucher($request)->total() ?? 0;
    }

    public function totalFuture(Request $request)
    {
        $request->type = TypeVoucher::FUTURE->value;
        return $this->voucherService->getListVoucher($request)->total() ?? 0;
    }

    public function getTypesAndStatus()
    {
        return [
            'status' => StatusVoucher::cases(),
            'types' => TypeVoucher::cases()
        ];
    }

    public function getUserTradingVolume(Request $request)
    {
        $user = $request->user();
        $trading = Leaderboard::query()
            ->selectRaw('COALESCE(SUM(trading_volume), 0) as volumes')
            ->where('user_id', $user->id)
            ->first();

        return $trading->volumes;
    }

    public function addBalanceVoucherFuture(Request $request)
    {
        try {
            $this->voucherService->addBalanceVoucherFuture($request->all());

            return $this->sendResponse(true);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }
}
