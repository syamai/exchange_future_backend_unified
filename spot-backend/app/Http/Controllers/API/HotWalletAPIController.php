<?php
namespace App\Http\Controllers\API;

use App\Http\Requests\CreateEOSAddressRequest;
use App\Http\Services\HotWalletService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\AppBaseController;
use GuzzleHttp\Exception\RequestException;

class HotWalletAPIController extends AppBaseController
{
    protected $hotWalletService;
    public function __construct(HotWalletService $hotWalletService)
    {
        $this->hotWalletService = $hotWalletService;
    }
    public function createEOSReceiveAddress(CreateEOSAddressRequest $request)
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $this->hotWalletService->createEOSReceiveAddress($params);
            DB::commit();
            return 'ok';
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $e;
        }
    }
}
