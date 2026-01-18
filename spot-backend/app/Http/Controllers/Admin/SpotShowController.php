<?php
namespace App\Http\Controllers\Admin;

use App\Consts;
use App\IdentifierHelper;
use App\Http\Services\SpotService;
use App\Http\Services\ExportExelService;
use App\Http\Controllers\AppBaseController;

use Illuminate\Http\Request;

class SpotShowController extends AppBaseController
{
    private $spotService;
    private $identifierHelper;

    public function __construct(SpotService $spotService, ExportExelService $exportExelService, IdentifierHelper $identifierHelper)
    {
        $this->spotService = $spotService;
        $this->exportExelService = $exportExelService;
        $this->identifierHelper = $identifierHelper;
    }

    public function getlist(Request $request) {
        $spot = $this->spotService;
        $result = $spot->list($request);
        return $this->sendResponse($result);
    }

    public function getParamsOrderbook(Request $request) {
        $spot = $this->spotService;
        $result = $spot->params($request, 1);
        return $this->sendResponse($result);
    }
    public function getParamsOrdersHistory(Request $request) {
        $spot = $this->spotService;
        $result = $spot->params($request, 2);
        return $this->sendResponse($result);
    }
    public function getParamsOrdersTradeHistory(Request $request) {
        $spot = $this->spotService;
        $result = $spot->params($request, 3);
        return $this->sendResponse($result);
    }
    public function getParamsOrdersOpen(Request $request) {
        $spot = $this->spotService;
        $result = $spot->params($request, 4);
        return $this->sendResponse($result);
    }
    public function getParamswithdraw(Request $request) {
        $spot = $this->spotService;
        $result = $spot->params($request, 5);
        return $this->sendResponse($result);
    }
    public function getParamsdeposit(Request $request) {
        $spot = $this->spotService;
        $result = $spot->params($request, 6);
        return $this->sendResponse($result);
    }
    public function getOrdersHistory(Request $request) {
        $spot = $this->spotService;
        $result = $spot->ordersHistory($request);
        return $this->sendResponse($result);
    }
    public function tradeOrdersHistory(Request $request) {
        $spot = $this->spotService;
        $result = $spot->ordersTradeHistory($request);
        return $this->sendResponse($result);
    }
    public function withdraw(Request $request) {
        $spot = $this->spotService;
        $request['trans_type'] = Consts::TRANSACTION_TYPE_WITHDRAW;
        $result = $spot->withdraw($request);
        return $this->sendResponse($result);
    }
    public function deposit(Request $request) {
        $spot = $this->spotService;
        $request['trans_type'] = Consts::TRANSACTION_TYPE_DEPOSIT;
        $result = $spot->desposit($request);
        return $this->sendResponse($result);
    }
    public function OrdersOpen(Request $request) {
        $spot = $this->spotService;
        $result = $spot->ordersOpen($request);
        return $this->sendResponse($result);
    }
    public function responseExport($export) {
        if($export) return $this->sendResponse($export);
        return $this->sendIsEmpty("export: IsEmpty");

    }
    public function exportOrdersHistory(Request $request) {
        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();
        $ext = $request->ext ?? 'csv';
        $export = $this->exportExelService->export($request, "exportSpotOrdersHistory_{$uniqueIdentifier}.{$ext}", $ext, 1);

        return $this->responseExport($export);
    }
    public function exportOrdersTradeHistory(Request $request) {
        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();
        $ext = $request->ext ?? 'csv';
        $export = $this->exportExelService->export($request, "exportSpotOrdersTradeHistory_{$uniqueIdentifier}.{$ext}", $ext, 2);

        return $this->responseExport($export);
    }
    public function exportOrdersOpen(Request $request) {
        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();
        $ext = $request->ext ?? 'csv';
        $export = $this->exportExelService->export($request, "exportSpotOrdersOpen_{$uniqueIdentifier}.{$ext}", $ext, 3);

        return $this->responseExport($export);
    }
    public function exportWithdraw(Request $request) {
        $request['trans_type'] = Consts::TRANSACTION_TYPE_WITHDRAW;
        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();

        $ext = $request->ext ?? 'csv';
        $export = $this->exportExelService->export($request, "exportSpotTransactionsWithdraw_{$uniqueIdentifier}.{$ext}", $ext, 4);

        return $this->responseExport($export);
    }
    public function exportDeposit(Request $request) {
        $request['trans_type'] = Consts::TRANSACTION_TYPE_DEPOSIT;
        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();

        $ext = $request->ext ?? 'csv';
        $export = $this->exportExelService->export($request, "exportSpotTransactionsDeposit_{$uniqueIdentifier}.{$ext}", $ext, 5);

        return $this->responseExport($export);
    }
     public function cancelOrder(Request $request) {
        $spot = $this->spotService;
        try {
            $spot->cancelOrder($request['id']);
            return $this->sendResponse("");
        }catch (\Exception $error) {
            return $this->sendResponse($error);
        }
     }
}
