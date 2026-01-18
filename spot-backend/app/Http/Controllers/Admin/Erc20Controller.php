<?php
/**
 * Created by PhpStorm.
 * Date: 5/7/19
 * Time: 1:45 PM
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\RegisterNewErc20;
use App\Http\Services\RegisterErc20Service;
use App\Models\CoinsConfirmation;
use Illuminate\Http\Request;

use SotaWallet\SotaWalletService;

class Erc20Controller extends AppBaseController
{
    private $registerErc20Service;

    public function __construct(RegisterErc20Service $registerErc20Service)
    {
        $this->registerErc20Service = $registerErc20Service;
    }

    public function registerErc20(RegisterNewErc20 $request)
    {
        try {
            $params = $request->all();
            $data = $this->registerErc20Service->register($params);

            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception);
        }
    }

    public function getErc20ContractInformation(Request $request)
    {
        try {
            $contractAddress = $request->input('contract_address');
            $network = $request->input('network');
            $res             = SotaWalletService::getErc20Information($contractAddress, $network);
            $data            = collect($res)->toArray();

            $coin  = $data['networkSymbol'];
            $count = $this->countCurrency($coin);

            if ($count > 0) {
                $data['error'] = 'symbol.exist';
                return $this->sendError($data);
            }

            return $this->sendResponse(collect($res)->toArray());
        } catch (\Exception $e) {
            logger($e);
            throw $e;
        }
    }
    public function countCurrency($coin)
    {
        return CoinsConfirmation::where(compact('coin'))->count();
    }
}
