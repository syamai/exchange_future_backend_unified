<?php
/**
 * Created by PhpStorm.

 * Date: 6/22/19
 * Time: 5:39 PM
 */

namespace App\Facades;

use App\Exports\AML_Transactions;
use App\Exports\TransactionHistory;
use App\Exports\OrderHistory;
use App\Exports\OrderTransaction;
use App\Exports\PendingOrder;
use App\Exports\ReturnRate;
use App\Exports\USDWithdrawals;
use App\Utils;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

class UploaderMethod
{
    /**
     * ----> WalletController@exportExcel
     * @param $fileName
     * @param $rows
     * @throws \Maatwebsite\Excel\Exceptions\LaravelExcelException
     */
    public function transactionHistory($fileName, $rows): void
    {
        $file = ExcelFacade::raw(new TransactionHistory($rows, $fileName), Excel::CSV);

        Storage::disk('s3')->put($fileName, $file, 'public');
    }

    /**
     * ----> OrderController@buildExcelFile
     * @param $fileName
     * @param $rows
     * @throws \Maatwebsite\Excel\Exceptions\LaravelExcelException
     */

    public function orderHistory($fileName, $rows): void
    {
        $file = ExcelFacade::raw(new OrderHistory($rows, $fileName, 'Order Transaction'), Excel::CSV);

        Storage::disk('s3')->put($fileName, $file, 'public');
    }

    /**
     * ---> ReferralController@buildExcelFile
     * @param $rows
     * @param $fileName
     * @throws \Maatwebsite\Excel\Exceptions\LaravelExcelException
     */

    public function referral($rows, $fileName): void
    {
        $file = ExcelFacade::raw(new OrderTransaction($rows, $fileName, 'Order Transaction'), Excel::CSV);

        Storage::disk('s3')->put($fileName, $file, 'public');
    }

    /**
     * ---> OrderController@downloadOrderPending
     * @param $rows
     * @throws \Maatwebsite\Excel\Exceptions\LaravelExcelException
     */

    public function pendingOrder($rows): void
    {
        $fileName = 'PendingOrder';

        $file = ExcelFacade::raw(new PendingOrder($rows, $fileName, 'Pending Order'), Excel::XLSX);

        Storage::disk('s3')->put($fileName, $file, 'public');
    }

    /**
     * ----> ProfitAndLossController@exportToExcel
     * @param $rows
     * @throws \Maatwebsite\Excel\Exceptions\LaravelExcelException
     */

    public function returnRate($rows): void
    {
        $fileName = 'ReturnRate';

        $file = ExcelFacade::raw(new ReturnRate($rows, $fileName, 'Profit And Loss'), Excel::XLSX);

        Storage::disk('s3')->put($fileName, $file, 'public');
    }

    /**
     * ---> AmlTransactionApiController@exportExcel
     * @param $data
     */

    public function amalTransaction($data): void
    {
        $fileName = "AML_Transactions";

        $file = ExcelFacade::store(new AML_Transactions($data, $fileName, 'Sheetname'), storage_path('app/public/excel/exports' . auth()->id()));

        Storage::disk('s3')->put($fileName, $file, 'public');
    }

    /**
     * ----> AdminController@exportUsdTransactionsToExcel
     * @param $rows
     * @throws \Maatwebsite\Excel\Exceptions\LaravelExcelException
     */

    public function usdWithdraw($rows): void
    {
        $fileName = 'USD Withdrawals';

        $file = ExcelFacade::raw(new USDWithdrawals($rows, $fileName, 'Transactions'), Excel::XLSX);

        Storage::disk('s3')->put($fileName, $file, 'public');
    }

    /**
     * ServiceCenterAPIController@
     */

    public function image($pathFolder, $file): string
    {
        $storagePath = 'public' . DIRECTORY_SEPARATOR . $pathFolder;

        $filename = Utils::currentMilliseconds() . '.' . $file->getClientOriginalExtension();

        if (!empty($prefixName)) {
            $filename = $prefixName . '.' .$filename;
        }

        Storage::disk('s3')->storeAs($storagePath, $filename);

        return "/storage/$pathFolder/$filename";
    }
}
