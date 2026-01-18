<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\VoucherClaimRequest;
use App\Enums\StatusVoucher;
use App\Http\Services\UserService;
use App\Http\Services\VoucherService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class VoucherController extends AppBaseController
{
    private $voucherService;
    private $userService;
    public function __construct(VoucherService $voucherService, UserService $userService)
    {
        $this->voucherService = $voucherService;
        $this->userService = $userService;
    }

/**
     * @OA\Post(
     *     path="/api/v1/vouchers/claim",
     *     summary="[Private] Claim a voucher",
     *     description="Claim a voucher",
     *     tags={"Private API"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="voucher_id", description="voucher id", type="number", example="4")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Claim success",
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
    public function claim(VoucherClaimRequest $request) {
        $userVoucher = $this->voucherService->getUserVoucher($request, StatusVoucher::AVAILABLE->value, true);
        if ($userVoucher) {
            $voucher = DB::table('vouchers')->where('id', $userVoucher->voucher_id)->first();
            if ($voucher) {
                $result = $this->userService->updateBalance($userVoucher);
                if ($result) {
                    return $this->sendResponse($result);
                }
            }
        }

        return $this->sendError(false);
    }

    public function claimFuture(VoucherClaimRequest $request)
    {
        $userVoucher = $this->voucherService->getUserVoucher($request, StatusVoucher::AVAILABLE->value, true);
        try {
            if ($userVoucher) {
                $voucher = DB::table('vouchers')->where('id', $userVoucher->voucher_id)->first();
                if ($voucher) {
//                $result = $this->userService->updateBalanceFuture(strtolower($voucher->currency), $userVoucher, $request);
//                if ($result) {
//                    return $this->sendResponse($result);
//                }

                    DB::table('user_vouchers')->where('voucher_id', $userVoucher->voucher_id)
                        ->where('user_id', Auth::id())
                        ->update([
                            'status' => StatusVoucher::REDEEMED->value
                        ]);
                    Artisan::call('producer:send-reward-future', [
                        'userId' => Auth::id(),
                        'amount' => $userVoucher->amount_old,
                        'currency' => strtolower($voucher->currency)
                    ]);

                    return $this->sendResponse(true);
                }
            }

            return $this->sendError(false);
        } catch (Exception $exception) {
            return [
                'status' => false,
                'msg' => $exception->getMessage()
            ];
        }
    }
}
