<?php

namespace App\Http\Controllers;

use App\Http\Services\PriceService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Services\OrderService;
use App\Utils;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use App\Consts;
use App\Exports\OrderTransaction;
use App\Exports\PendingOrder;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Maatwebsite\Excel\Excel;

class OrderController extends Controller
{
    private OrderService $orderService;
    private PriceService $priceService;

    public function __construct()
    {
        $this->orderService = new OrderService();
        $this->priceService = new PriceService();
    }

    public function exportToExcelForUser(Request $request)
    {
        $params = $request->all();
        $userId = $request->input('user_id', -1);
        $transactions = $this->orderService->getTransactionsForExport($params, $userId);
        $this->buildExcelFile($transactions, $params['timezone_offset']);
    }

    private function buildExcelFile(array $rows, string $fileName): bool
    {
        return ExcelFacade::store(
            new OrderTransaction($rows, $fileName, 'Order Transaction'),
            $this->getFilePath(),
            null,
            Excel::CSV
        );
    }

    public function exportToCSVOrderHistory(Request $request)
    {
        $params = $request->all();
        $timzoneOffset = $request->input('timezone_offset', Carbon::now()->offset);
        if (!empty($params['start_date']) && !empty($params['end_date'])) {
            list($params['start_date'], $params['end_date']) = $this->getDateRange(
                $params['start_date'],
                $params['end_date'],
                $timzoneOffset
            );
        }
        $transactions = $this->orderService->getTransactionsForExport($params, Auth::id());
        $rows = [];
        //Insert title column
        $rows[] = [
            __('Date'),
            __('Pair'),
            __('Type'),
            __('Side'),
            __('Average'),
            __('Price'),
            __('Filled'),
            __('Amount'),
            __('Total'),
            __('Trigger Conditions'),
            __('Status')
        ];

        foreach ($transactions as $transaction) {
            $rows[] = array(
                "'" . Utils::millisecondsToDateTime($transaction->updated_at, $timzoneOffset, 'd-m-Y H:i:s'),
                strtoupper("{$transaction->coin}/{$transaction->currency}"),
                ucfirst($transaction->type),
                Utils::tradeType($transaction->trade_type),
                $transaction->executed_price,
                $transaction->price,
                $transaction->executed_quantity,
                $transaction->quantity,
                Utils::mulBigNumber($transaction->executed_quantity, $transaction->executed_price),
                Utils::getTriggerConditionStatus($transaction->stop_condition) . " " . Utils::trimFloatNumber($transaction->base_price),
                Utils::getOrderStatusV2($transaction),
            );
        }
        $fileName = 'OrderHistory_' . Utils::currentMilliseconds();

        return ExcelFacade::download(new OrderTransaction($rows, $fileName, 'Order Transaction'), $fileName,
            Excel::CSV);
    }

    // maxDays 180 ~ 6 months
    public function getDateRange($startDate, $endDate, $timezoneOffset, $maxDays = 180)
    {
        if ($startDate < $endDate) {
            $minStartDate = Utils::millisecondsToCarbon($endDate, $timezoneOffset)->subDay($maxDays)->timestamp * 1000;
            if ($startDate < $minStartDate) {
                $startDate = $minStartDate;
            }
        }
        return array($startDate, $endDate);
    }

    public function exportCSVTradeHistory(Request $request)
    {
        $params = $request->all();
        $timzoneOffset = $request->input('timezone_offset', Carbon::now()->offset);
        if (!empty($params['start_date']) && !empty($params['end_date'])) {
            list($params['start_date'], $params['end_date']) = $this->getDateRange(
                $params['start_date'],
                $params['end_date'],
                $timzoneOffset
            );
        }
        $transactions = $this->orderService->getTradingHistoriesForExport($params);
        $rows = [];
        //Insert title column
        $rows[] = [__('Date'), __('Pair'), __('Side'), __('Price'), __('Filled'), __('Fee'), __('Total')];

        foreach ($transactions as $transaction) {
            $rows[] = array(
                "'" . Utils::millisecondsToDateTime($transaction->created_at, $timzoneOffset, 'd-m-Y H:i:s'),
                strtoupper("{$transaction->coin}/{$transaction->currency}"),
                Utils::tradeType($transaction->trade_type),
                $transaction->price,
                $transaction->quantity,
                $transaction->fee . ' ' . strtoupper($transaction->coin),
                Utils::mulBigNumber($transaction->quantity, $transaction->price),
            );
        }
        $fileName = 'TradeHistory_' . Utils::currentMilliseconds();

        return ExcelFacade::download(new OrderTransaction($rows, $fileName, 'Order Transaction'), $fileName,
            Excel::CSV);
    }

    public function downloadOrderPending(Request $request)
    {
        $params = $request->all();
        $timzoneOffset = $params['timezone_offset'];
        $rows = [];

        $currency = $request->input('currency', 'usd');
        $orders = Order::where('user_id', Auth::id())
            ->when($currency, function ($query) use ($currency) {
                $query->where('currency', $currency);
            })
            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
            ->get();

        //Insert title column
        $rows[] = [
            __('Pair'),
            __('Time'),
            __('Type'),
            __('Quantity'),
            __('Remain'),
            __('Order Price'),
            __('Total'),
            __('Current Price')
        ];

        foreach ($orders as $order) {
            $remainQuantity = $order->quantity - $order->executed_quantity;
            $orderAmount = $order->price * $order->quantity;
            $rows[] = array(
                "{$order->coin}/{$order->currency}",
                Utils::millisecondsToDateTime($order->created_at, $timzoneOffset, 'd-m-Y H:i:s'),
                $order->trade_type,
                "{$order->quantity} {$order->coin}",
                "{$remainQuantity} {$order->coin}",
                "{$order->price} {$order->currency}",
                "{$orderAmount} {$order->currency}",
                $this->priceService->getPrice($order->currency, $order->coin)->price
            );
        }

        ExcelFacade::download(
            new PendingOrder($rows, 'downloadPendingOrder.xlsx', 'Pending Order'),
            'downloadPendingOrder.xlsx',
            Excel::XLSX,
            ['Content-Type' => 'text/csv']
        );
    }
}
