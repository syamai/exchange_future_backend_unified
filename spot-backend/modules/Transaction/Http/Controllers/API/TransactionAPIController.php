<?php
/**
 * Created by PhpStorm.
 * Date: 5/2/19
 * Time: 4:01 PM
 */

namespace Transaction\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\CreateWithdrawalAPIRequest;
use App\Http\Services\EnableWithdrawalSettingService;
use App\Models\UserWithdrawalAddress;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Transaction\Http\Services\DepositService;
use Transaction\Http\Services\TransactionService;
use Transaction\Http\Services\WalletService;
use Transaction\Http\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionAPIController extends AppBaseController
{
    /**
     * @var WithdrawalService
     */
    private $withdrawalService;
    /**
     * @var DepositService
     */
    private $depositService;

    public function __construct(
        WithdrawalService $withdrawalService,
        DepositService $depositService
    ) {
        $this->withdrawalService = $withdrawalService;
        $this->depositService = $depositService;
    }

    public function getDepositHistory(Request $request)
    {
        $data = $this->depositService->getDepositHistory($request->all());
        return $this->sendResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/withdraw",
     *     summary="[Private] Withdraw",
     *     description="Withdraw money from your account",
     *     tags={"Account"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="currency", description="Currency name", type="string", example="btc"),
     *              @OA\Property(property="amount", description="Amount of withdraw", type="decimal", example=-0.01),
     *              @OA\Property(
     *                  property="blockchain_address",
     *                  description="Your blockchain address you want withdraw token to",
     *                  type="decimal",
     *                  example="0x95c176E66b035E3cF7C6F486960.............."
     *              ),
     *              @OA\Property(
     *                  property="otp",
     *                  description="OTP code of 2FA authentication, This is required if enable google otp",
     *                  type="string",
     *                  example="030830"
     *              ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Withdraw success",
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
     *                example={
     *                  "id": 1,
     *                  "transaction_id": "26d38c6dbb95546f4ffb4a655d9d822f13d4ae31",
     *                  "user_id": 1013,
     *                  "tx_hash": "0x9189e53086b6c3f55d00d4d4a395788768bf26d5f62d2112bef3340f9d9a3d5e",
     *                  "currency": "eth",
     *                  "amount": "0.5000000000",
     *                  "fee": "0.0000000000",
     *                  "status": "pending",
     *                  "from_address": "0xGhtB83541D3Cc05722d9E8b857596465741234",
     *                  "to_address": "0xbCa0B83541D3Cc05722d9E8b8575964657498E7a",
     *                  "blockchain_sub_address": null,
     *                  "is_external": 1,
     *                  "transaction_date": "2019-08-22",
     *                  "created_at": 1566444899923,
     *                  "updated_at": 1566444025879
     *                }
     *             ),
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
    public function withdraw(CreateWithdrawalAPIRequest $request)
    {
        DB::beginTransaction();
        try {
            $params = $request->all();

            if ($request->input('is_new_address')) {
                $this->syncUserWithdrawalAddress($params);
            }

            $user = $request->user();
            $transaction = $this->withdrawalService->withdraw($user, \Arr::get($params, 'currency'), $params);
            DB::commit();

            return $this->sendResponse($transaction);
        } catch (HttpException $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    private function syncUserWithdrawalAddress($params)
    {
        $currency = \Arr::get($params, 'currency');
        $networkId = \Arr::get($params, 'network_id');
        $address = \Arr::get($params, 'blockchain_address');
        $sub_address = \Arr::get($params, 'blockchain_sub_address');
        $exist = UserWithdrawalAddress::where('user_id', Auth::id())
            ->where('coin', $currency)
            ->where('network_id', $networkId)
            ->where('wallet_address', $address)
            ->where('wallet_sub_address', $sub_address)
            ->count();
        if ($exist) {
            throw ValidationException::withMessages([
                'blockchain_address' => ['validation.unique_withdrawal_address'],
            ]);
        }

        $userWithdrawalAddress = new UserWithdrawalAddress();
        $userWithdrawalAddress->coin = $currency;
        $userWithdrawalAddress->network_id = $networkId;
        $userWithdrawalAddress->user_id = Auth::id();
        $userWithdrawalAddress->wallet_address = $address;
        $userWithdrawalAddress->wallet_name = \Arr::get($params, 'wallet_name');
        $userWithdrawalAddress->wallet_sub_address = $sub_address;

        $userWithdrawalAddress->save();

        return true;
    }
}
