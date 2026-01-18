<?php

namespace App\Http\Services;

use App\Exports\SpotAccountKycListExport;
use App\Exports\SpotAccountListExport;
use App\Exports\SpotOrdersOpenExport;
use App\Exports\SpotTransactionsDepositExport;
use App\Exports\SpotTransactionsWithdrawExport;
use Illuminate\Support\Facades\Storage;
use App\Exports\SpotOrdersHistoryExport;
use App\Exports\SpotOrdersTradeHistoryExport;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

class ExportExelService
{
    private $spotService;
    public function __construct(SpotService $spotService)
    {
        $this->spotService = $spotService;
    }

    public function export($request, $fileName, $type, $case, $data = [])
    {
        if ($type == 'csv') $type = ExcelType::CSV;
        if ($type == 'xlsx') $type = ExcelType::XLSX;

        switch ($case) {
            case 1:
                $data = $this->spotService->ordersHistory($request, -1);
                $fileName = self::ordersHistory($data, $fileName, $type);
                break;
            case 2:
                $data = $this->spotService->ordersTradeHistory($request, -1);
                $fileName = self::ordersTradeHistory($data, $fileName, $type);
                break;
            case 3:
                $data = $this->spotService->ordersOpen($request, -1);
                $fileName = self::ordersOpen($data, $fileName, $type);
                break;
            case 4:
                $data = $this->spotService->withdraw($request, -1);
                $fileName = self::withdraw($data, $fileName, $type);
                break;
            case 5:
                $data = $this->spotService->desposit($request, -1);
                $fileName = self::deposit($data, $fileName, $type);
                break;
            case 6:
                $fileName = self::accountList($data, $fileName, $type);
                break;
            case 7:
                $fileName = self::accountKycList($data, $fileName, $type);
                break;
        }

        if($fileName) {
            $url = Storage::disk('public')->url($fileName);
            preg_match("/^.*\/(storage\/.*)$/", $url, $matches);

            return ['file' => $fileName, 'path' => $matches[1]];
        } else return [];

    }
    public function ordersHistory($data, $fileName, $type) {
        $data = $data->toArray();
        $headings = $data ? array_keys($data[0]) : [];
        if (!$data) return [];

        FacadesExcel::store(new SpotOrdersHistoryExport($data, $fileName, 'SpotOrderHistory', $headings), $fileName, 'public', $type);
        return $fileName;
    }

    public function ordersTradeHistory($data, $fileName, $type) {
        $data = $data->toArray();
        $headings = $data ? array_keys($data[0]) : [];
        if (!$data) return [];

        FacadesExcel::store(new SpotOrdersTradeHistoryExport($data, $fileName, 'SpotOrdersTradeHistory', $headings), $fileName, 'public', $type);
        return $fileName;
    }

    public function ordersOpen($data, $fileName, $type) {
        $data = $data->toArray();
        $headings = $data ? array_keys($data[0]) : [];
        if (!$data) return [];

        FacadesExcel::store(new SpotOrdersOpenExport($data, $fileName, 'SpotOrdersOpen', $headings), $fileName, 'public', $type);
        return $fileName;
    }
    public function withdraw($data, $fileName, $type) {
        $data = $data->toArray();
        $headings = $data ? array_keys($data[0]) : [];
        if (!$data) return [];

        FacadesExcel::store(new SpotTransactionsWithdrawExport($data, $fileName, 'SpotTransactionsWithdraw', $headings), $fileName, 'public', $type);
        return $fileName;
    }
    public function deposit($data, $fileName, $type) {
        $data = $data->toArray();
        $headings = $data ? array_keys($data[0]) : [];
        if (!$data) return [];

        FacadesExcel::store(new SpotTransactionsDepositExport($data, $fileName, 'SpotTransactionsDeposit', $headings), $fileName, 'public', $type);
        return $fileName;
    }

    public function accountList($data, $fileName, $type) {
		$data = $data->map(function ($item) {
			unset($item['phone_no']);
			unset($item['mobile_code']);
			unset($item['phone_number']);
			return $item;
		});
        $data = $data->toArray();
        $headings = $data ? array_keys($data[0]) : [];
        if (!$data) return [];

        FacadesExcel::store(new SpotAccountListExport($data, $fileName, 'SpotAccountList', $headings), $fileName, 'public', $type);
        return $fileName;
    }

    public function accountKycList($data, $fileName, $type) {
        $data = $data->toArray();
        $headings = $data ? array_keys($data[0]) : [];
        if (!$data) return [];

        FacadesExcel::store(new SpotAccountKycListExport($data, $fileName, 'SpotAccountKycList', $headings), $fileName, 'public', $type);
        return $fileName;
    }
}
