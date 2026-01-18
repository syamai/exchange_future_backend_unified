<?php

namespace App\Http\Controllers;

use App\Exports\WalletExport;
use App\Utils;
use App\Consts;
use Illuminate\Http\Request;

use Carbon\Carbon;
use App\Http\Services\TransactionService;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

class WalletController extends AppBaseController
{
    private TransactionService $walletService;

    public function __construct(TransactionService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function goToZendesk(Request $request)
    {
        $request->user();
    }

    public function exportExcel(Request $request, $currency = null): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $params = $request->all();
        $params['currency'] = $currency;
        $timzoneOffset = $request->input('timezone_offset', Carbon::now()->offset);

        $transactions = $this->walletService->exportHistory($params);

        $rows = $this->getTitle($currency);

        foreach ($transactions as $transaction) {
            $amount = abs($transaction->amount);
            $status = match($transaction->status) {
                'pending' => __('common.status.pending') ,
                'success' => __('common.status.success'),
                'cancel' => __('common.status.cancel'),
                'error' => __('common.status.failed')
            };
            $address = $transaction->to_address;
            $tag = $transaction->blockchain_sub_address;
            $txid = $transaction->transaction_id;

            // format date
            $ymd = Utils::millisecondsToDateTime($transaction->created_at, $timzoneOffset, 'Y-m-d H:i:s');

            $temp = array($status, $amount, $ymd, $address, $tag, $txid);

            if (!$currency) {
                array_splice($temp, 1, 0, strtoupper($transaction['currency']));
            }

            array_push($rows, $temp);
        }

        $file_name = 'wallet_transaction';
        if (array_key_exists('type', $params) && (!empty($params['type']))) {
            if ($params['type'] == Consts::TRANSACTION_TYPE_DEPOSIT) {
                $file_name = 'deposit_history';
            } else {
                $file_name = 'withdrawal_history';
            }
        }
        $fileName = "{$file_name}_" . Utils::currentMilliseconds();

        return ExcelFacade::download(new WalletExport($rows, $fileName), $fileName,Excel::CSV);
    }

    private function getTitle($currency): array
    {
        if ($currency) {
            return array(array(__('Time'), __('Deal ID'), __('Deposit'), __('Withdraw'), __('Fee'), __('State')));
        } else {
            return array(array(__('funds.deposit_usd.transaction_history_table.status'),
            __('funds.history.coin'),
            __('funds.deposit_usd.transaction_history_table.amount'),
            __('funds.history.date'),
            __('funds.history.address'),
            __('funds.history.tag'),
            __('funds.history.txid')));
        }
    }
}
