<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Utils\OrderbookUtil;
use App\Http\Services\MasterdataService;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateOrderBook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $action;
    private $rows;
    private $currency;
    private $coin;
    private $updatedRows;

    private $orderService;
    private $priceService;

    private $epsilon = 1e-10;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($action, $rows, $currency, $coin)
    {
		if (!env("PROCESS_ORDER_REQUEST_REDIS", false)) {
			$this->onConnection(Consts::CONNECTION_RABBITMQ);
		}
        $this->action = $action;
        $this->rows = $rows;
        $this->currency = $currency;
        $this->coin = $coin;
        $this->updatedRows = [
            'buy' => [],
            'sell' => []
        ];

        $this->orderService = new OrderService();
        $this->priceService = new PriceService();
    }

    public static function dispatchIfNeed($action, $rows, $currency, $coin)
    {
        return UpdateOrderBook::dispatch($action, $rows, $currency, $coin)->onQueue(Consts::QUEUE_ORDER_BOOK);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $priceGroups = MasterdataService::getOneTable('price_groups')
            ->filter(function ($value, $key) {
                return $value->currency == $this->currency && $value->coin == $this->coin;
            })
            ->pluck('value');

        if (empty($priceGroups)) return;

        foreach ($this->rows as $row) {
            $this->orderService->updateOrderbookGroup(
                $row,
                $priceGroups
            );
        }
        foreach ($priceGroups as $tickerSize) {
            $shouldReload = true; //$orders->count() > Consts::ORDER_BOOK_SIZE * 10;
            if ($shouldReload) {
                $this->reloadOrderBook($this->rows, $tickerSize);
            } else {
                $this->updateOrderBook($this->rows, $tickerSize);
            }
        }
    }

    private function isIncreaseAction()
    {
        switch ($this->action) {
            case Consts::ORDER_BOOK_UPDATE_CREATED:
            case Consts::ORDER_BOOK_UPDATE_ACTIVATED:
                return true;
            case Consts::ORDER_BOOK_UPDATE_CANCELED:
            case Consts::ORDER_BOOK_UPDATE_MATCHED:
                return false;
            default:
                throw new \Exception('Unknonw update orderbook action: ' . $this->action);
        }
    }

    private function reloadOrderBook($orders, $tickerSize)
    {
        $key = OrderService::getOrderBookKey($this->currency, $this->coin, $tickerSize);
        Cache::forget($key);
        $job = SendOrderBook::dispatchIfNeed($this->currency, $this->coin, $tickerSize);
        // if ($job) {
        //     $job->onConnection($this->connection);
        // }
        // event(new OrderBookUpdated($orderBook, $this->currency, $this->coin, $tickerSize, true));
    }

    private function updateOrderBook($orders, $tickerSize)
    {
        $key = OrderService::getOrderBookKey($this->currency, $this->coin, $tickerSize);
        $orderBook = $this->orderService->getOrderBook($this->currency, $this->coin, $tickerSize);

        switch ($this->action) {
            case Consts::ORDER_BOOK_UPDATE_CREATED:
                $this->updateOrderBookOnOrderCreated($orderBook, $orders, $tickerSize);
                break;
            case Consts::ORDER_BOOK_UPDATE_CANCELED:
                $this->updateOrderBookOnOrderCanceled($orderBook, $orders, $tickerSize);
                break;
            case Consts::ORDER_BOOK_UPDATE_MATCHED:
                $this->updateOrderBookOnOrdersMatched($orderBook, $orders, $tickerSize);
                break;
            case Consts::ORDER_BOOK_UPDATE_ACTIVATED:
                $this->updateOrderBookOnOrdersActivated($orderBook, $orders, $tickerSize);
                break;
            default:
                //TODO error
        }
        Cache::forever($key, $orderBook);
        if ($this->orderService->shouldReloadOrderBook($this->currency, $this->coin, $tickerSize)) {
            $this->reloadOrderBook($orders, $tickerSize);
        } else {
            $job = SendOrderBook::dispatchIfNeed($this->currency, $this->coin, $tickerSize);
            if ($job) {
                $job->onConnection($this->connection);
            }
        }
        // event(new OrderBookUpdated($this->updatedRows, $this->currency, $this->coin, $tickerSize, false));
    }

    private function addUpdatedRow($tradeType, $row)
    {
        if (!$row) {
            return;
        }

        if ($tradeType == Consts::ORDER_TRADE_TYPE_BUY) {
            array_push($this->updatedRows['buy'], $row);
        } else {
            array_push($this->updatedRows['sell'], $row);
        }
    }

    private function updateOrderBookOnOrderCreated(&$orderBook, $orders, $tickerSize)
    {
        if (!$orders->count()) {
            return;
        }
        $order = $orders[0];
        $row = $this->updateForOrder($orderBook, $order, $tickerSize);
        $this->addUpdatedRow($order->trade_type, $row);
    }

    private function updateOrderBookOnOrderCanceled(&$orderBook, $orders, $tickerSize)
    {
        if (!$orders->count()) {
            return;
        }
        $order = $orders[0];
        $row = $this->updateForOrder($orderBook, $order, $tickerSize);
        $this->addUpdatedRow($order->trade_type, $row);
    }

    private function updateOrderBookOnOrdersMatched(&$orderBook, $orders, $tickerSize)
    {
        foreach ($orders as $order) {
            if ($order->price) {
                $row = $this->updateForOrder($orderBook, $order, $tickerSize);
                $this->addUpdatedRow($order->trade_type, $row);
            }
        }
    }

    private function updateOrderBookOnOrdersActivated(&$orderBook, $orders, $tickerSize)
    {
        foreach ($orders as $order) {
            $row = $this->updateForOrder($orderBook, $order, $tickerSize);
            $this->addUpdatedRow(Consts::ORDER_TRADE_TYPE_BUY, $row);
        }
    }

    private function updateForOrder(&$orderBook, $order, $tickerSize)
    {
        $tradeType = $order->trade_type;
        $price = OrderbookUtil::getPriceGroup($order->trade_type, $order->price, $tickerSize);
        // update current orderbook in cache
        if (!$this->shouldUpdateOrderBook($orderBook, $tradeType, $price, $tickerSize)) {
            Log::info("++++++++++++++++++++ DON'T update order book: $tradeType, price: $price, ticker: $tickerSize");
            return null;
        }
        Log::info("++++++++++++++++++++ update order book: $tradeType, price: $price, ticker: $tickerSize");
        $subOrderBook = $orderBook[$tradeType];
        $currentRow = $subOrderBook->filter(function ($value, $key) use ($price) {
            return Utils::isEqual($value->price, $price);
        })->first();
        if (!$currentRow) {
            $currentRow = $this->createNewRow($price);
            $subOrderBook[] = $currentRow;
            if ($tradeType == Consts::ORDER_TRADE_TYPE_BUY) {
                $subOrderBook = $subOrderBook->sortByDesc(function ($value, $key) {
                    return $value->price;
                });
            } else {
                $subOrderBook = $subOrderBook->sortBy(function ($value, $key) {
                    return $value->price;
                });
            }
            // if ($subOrderBook->count() > Consts::MAX_ORDER_BOOK_SIZE) {
            //     $subOrderBook->pop();
            // }
            $orderBook[$tradeType] = $subOrderBook;
        }

        $updatedRow = $this->orderService->getOrderGroup($this->currency, $this->coin, $tradeType, $price, $tickerSize);
        if ($updatedRow) {
            $currentRow->count = $updatedRow->count;
            $currentRow->quantity = $updatedRow->quantity;
        } else {
            $currentRow->count = 0;
            $currentRow->quantity = 0;
        }
        $orderBook[$tradeType] = $orderBook[$tradeType]->filter(function ($value, $key) {
            return $value->count > 0;
        });
        Log::info("++++++++++++++++++++ update order book row price: $currentRow->price, quantity: $currentRow->quantity, ticker: $tickerSize");
        return $currentRow;
    }

    private function shouldUpdateOrderBook($orderBook, $tradeType, $price, $tickerSize)
    {
        $meta = $orderBook['meta'][$tradeType];
        return $price >= $meta['min'] && $price < $meta['max'];
    }

    private function createNewRow($price)
    {
        return (object) [
            'count' => 0,
            'quantity' => 0,
            'price' => $price
        ];
    }
}
