<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\WithdrawalLimit;
use App\Models\Coin;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use App\Consts;
use App\Http\Services\MasterdataService;
use Illuminate\Http\JsonResponse;

class WithdrawalLimitController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $input = $request->all();
            $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
            $data = WithdrawalLimit::filter($input)->paginate($limit);
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $input = $request->all();
            $withdrawalLimit = WithdrawalLimit::find($id);
            if (empty($withdrawalLimit)) {
                return $this->sendError('id not found', 401);
            }
            $coin = Coin::where("coin", $withdrawalLimit->currency)->first();

            $decimal = @$coin->decimal ?? 0;
            if ($withdrawalLimit->currency == "usd") {
                $decimal = 2;
            }

            $checkDecimal = 0;
            if (!$this->checkDecimal($input, $decimal, $checkDecimal)) {
                return $this->sendError(__('exception.max_decimal_msg', ['max_decimal' => $decimal]), 401);
            };
            $data = WithdrawalLimit::where('id', $id)->update($input);
            MasterdataService::clearCacheOneTable('withdrawal_limits');
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }
    public function checkDecimal($params, $decimal, $checkDecimal): bool
    {
        foreach ($params as $param) {
            $tmp = (string)$param;
            if (strpos($tmp, ".") !== false) {
                $number = explode(".", $tmp);
                if (strlen($number[1]) > $checkDecimal) {
                    $checkDecimal = strlen($number[1]);
                };
            }
        }
        if ($checkDecimal > $decimal) {
            return false;
        }
        return true;
    }
}
