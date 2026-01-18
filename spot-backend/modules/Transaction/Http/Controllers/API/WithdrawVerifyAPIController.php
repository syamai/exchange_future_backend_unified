<?php
/**
 * Created by PhpStorm.
 * Date: 5/21/19
 * Time: 1:45 PM
 */

namespace Transaction\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Transaction\Http\Services\WithdrawVerifyService;

class WithdrawVerifyAPIController extends AppBaseController
{
    private $withdrawVerifyService;


    public function __construct(WithdrawVerifyService $withdrawVerifyService)
    {
        $this->withdrawVerifyService = $withdrawVerifyService;
    }

    public function verifyWithdraw($transactionId)
    {
        DB::beginTransaction();
        try {
            $transaction = $this->withdrawVerifyService->verifyWithdraw($transactionId);
            DB::commit();

            return $this->sendResponse($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            throw  $e;
        }
    }
}
