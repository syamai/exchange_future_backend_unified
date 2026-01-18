<?php
/**
 * Created by PhpStorm.

 * Date: 6/19/19
 * Time: 1:22 PM
 */

namespace Transaction\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use Transaction\Http\Services\UserBalanceService;

class UserBalanceAPIController extends AppBaseController
{
    private $userBalanceService;

    public function __construct(UserBalanceService $userBalanceService)
    {
        $this->userBalanceService = $userBalanceService;
    }

    public function getBalanceTransactionMain($currency)
    {
        $userId = auth('api')->id();
        try {
            $data = $this->userBalanceService->getBalanceTransactionMain($currency, $userId);
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }
    public function getDecimalCoin($currency)
    {
        try {
            $data = $this->userBalanceService->getDecimalCoin($currency);
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }
}
