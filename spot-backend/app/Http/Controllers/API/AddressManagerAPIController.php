<?php

namespace App\Http\Controllers\API;

use App\Http\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\UserService;
use App\Http\Requests\AddressManagerAPIRequest;
use App\Http\Requests\ChangeAddressWhiteList;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Consts;

class AddressManagerAPIController extends AppBaseController
{
    private $userService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function insertWalletAddress(AddressManagerAPIRequest $request): JsonResponse
    {
        $param = $request->all();
        $coin = $param['coin'];
        $networkId = $param['network_id'];
        $wallet_sub_address = $param['wallet_sub_address'];
        $wallet_address = $param['wallet_address'];

        $userWithdrawlAddress = \App\Models\UserWithdrawalAddress::where([
            'user_id'           => Auth::id(),
            'wallet_address'    => $wallet_address,
            'wallet_sub_address' => $wallet_sub_address,
            'coin'              => $coin,
            'network_id'              => $networkId,
        ]);
        if ($userWithdrawlAddress->exists()) {
            throw ValidationException::withMessages([
                'wallet_address' => ["This address has exist."],
            ]);
        };

        // check network support coin

        $networkCoin = DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->where([
                'c.coin' => $coin,
                'nc.network_id' => $networkId,
            ])
            ->select(['c.coin', 'nc.network_id'])
            ->first();
        if (!$networkCoin) {
            throw ValidationException::withMessages([
                'wallet_address' => ["Network not support {$coin}."],
            ]);
        }
        DB::beginTransaction();

        try {
            $data = $this->userService->insertWalletAddress($request->all());
            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateWalletsWhiteList(ChangeAddressWhiteList $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $idWallets = $request->input('idWallets');
//            $this->authorize('update', UserWithdrawalAddress::find($idWallets));

            $active = $request->input('active');
            $data = $this->userService->updateWalletsWhiteList($idWallets, $active);

            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function removeWalletsAddress(Request $request): JsonResponse
    {
        $idWallets = $request->input('idWallets');
        DB::beginTransaction();

        try {
//            $this->authorize('delete', UserWithdrawalAddress::find($idWallets));
            $data = $this->userService->removeWalletsAddress($idWallets);
            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
