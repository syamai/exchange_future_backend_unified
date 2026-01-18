<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 9/11/18
 * Time: 9:54 AM
 */

namespace App\Http\Controllers\API;

use App\Exports\AmlTransactionExport;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\AmlTransactionCreateRequest;
use App\Http\Services\AmlTransactionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Carbon\Carbon;
use App\Utils;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AmlTransactionApiController extends AppBaseController
{
    private AmlTransactionService $service;

    public function __construct(AmlTransactionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $input = $request->all();
        $data = $this->service->index($input);
        return $this->sendResponse($data);
    }

    public function store(AmlTransactionCreateRequest $request): JsonResponse
    {
        $input = $request->all();

        try {
            DB::beginTransaction();
            $amlTransaction = $this->service->store($input);
            DB::commit();

            return $this->sendResponse($amlTransaction);
        } catch (\Exception $exception) {
            DB::rollBack();

            if ($exception instanceof HttpResponseException) {
                throw new HttpException(422, $exception->getMessage());
            }

            throw $exception;
        }
    }

    public function getCashBack(Request $request): JsonResponse
    {
        $input = $request->all();
        $data = $this->service->getCashBack($input);
        return $this->sendResponse($data);
    }

    public function exportExcel(Request $request): JsonResponse
    {
        $input = $request->all();
        try {
            $currencyName = __('salespoint.buy_history.currency');
            $amountName = __('salespoint.buy_history.amount');
            $amalName = __('salespoint.buy_history.amal');
            $bonusName = __('salespoint.buy_history.bonus');
            $timeName = __('salespoint.buy_history.time');
            $emailName = __('salespoint.buy_history.email');
            $cashBackName = __('salespoint.buy_history.cash_back');

            $select = 'currency as `' . $currencyName .'`, payment as `' . $amountName .'`, amount as `'
                . $amalName .'`, bonus as `' . $bonusName .'`, created_at as `' . $timeName .'`';

            $data = $this->service->getDataby6Month($input, $select);
            foreach ($data as $item) {
                $item->{$currencyName} = strtoupper($item->{$currencyName});
                if ($item->{$currencyName} == 'USD') {
                    $item->{$amountName} = number_format($item->{$amountName}, 2);
                } else {
                    $item->{$amountName} = number_format($item->{$amountName}, 8);
                }
                $item->{$amalName} = number_format($item->{$amalName});
                $item->{$bonusName} = number_format($item->{$bonusName});

                $date = Carbon::parse($item->{$timeName})->timestamp * 1000;
                $timezoneOffset = $request->input('timezone_offset', Carbon::now()->offset);
                $date_time = Utils::millisecondsToDateTime($date, $timezoneOffset, 'Y-m-d H:i:s');
                $item->{$timeName} = $date_time;
            }

            $select1 = 'referred_email as `' . $emailName .'`, currency as `'
                . $currencyName .'`, bonus as `' . $cashBackName .'`, created_at as `' . $timeName .'`';
            $data1 = $this->service->getCashBackDataby6Month($input, $select1);
            foreach ($data1 as $item) {
                $item->{$currencyName} = strtoupper($item->{$currencyName});
                if ($item->{$currencyName} == 'USD') {
                    $item->{$cashBackName} = number_format($item->{$cashBackName}, 2);
                } else {
                    $item->{$cashBackName} = number_format($item->{$cashBackName}, 8);
                }

                $splited_str = explode('.', $item->{$emailName});
                $item->{$emailName} = substr($item->{$emailName}, 0, 2) . '***@***.' . end($splited_str);

                $date = Carbon::parse($item->{$timeName})->timestamp * 1000;
                $timezoneOffset = $request->input('timezone_offset', Carbon::now()->offset);
                $date_time = Utils::millisecondsToDateTime($date, $timezoneOffset, 'Y-m-d H:i:s');
                $item->{$timeName} = $date_time;
            }

            $title1 = [  __('salespoint.buy_history.currency') . '    ',
                __('salespoint.buy_history.amount') . '      ',
                __('salespoint.buy_history.amal') . '        ',
                __('salespoint.buy_history.bonus') . ' ',
                __('salespoint.buy_history.time') . '        '  ];
            $export_data1 = !empty($data->toArray()) ? $data->toArray() : $title1;

            $title2 = [  __('salespoint.buy_history.email') . '    ',
                __('salespoint.buy_history.currency') . '    ',
                __('salespoint.buy_history.cash_back') . '        ',
                __('salespoint.buy_history.time') . '        '  ];
            $export_data2 = !empty($data1->toArray()) ? $data1->toArray() : $title2;

            $dataExportExcel = [$export_data1, $export_data2];

            ExcelFacade::store(
                new AmlTransactionExport($dataExportExcel),
                storage_path('app/public/excel/exports' . auth()->id()). '/AMAL_Transactions.xlsx',
                Excel::XLSX
            );

            return $this->sendResponse(url('storage/excel/exports' . auth()->id() . '/AMAL_Transactions.xlsx'));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), 500);
        }
    }
}
