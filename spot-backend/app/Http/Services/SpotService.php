<?php
namespace App\Http\Services;

use App\Consts;
use App\IdentifierHelper;
use App\Jobs\ProcessOrderRequest;
use App\Jobs\ProcessOrderRequestRedis;
use App\Models\Order;
use App\Models\Orderbook;
use App\Models\OrderTransaction;

use App\Models\User;
use Brick\Math\BigNumber;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Transaction\Models\Transaction;

class SpotService {
    private $helper;
    private $coin, $currency, $trade_type, $filter_status_orders_history, $status_orders_open, $filter_status_orders_open, $types_orders;
    public function __construct(IdentifierHelper $helper)
    {
        $this->helper = $helper;
        $this->trade_type = $helper->tradeTypes();
        $this->coin = $helper->pairCoinEnable()->get('coins');
        $this->currency = $helper->pairCoinEnable()->get('currency');
        $this->types_orders = $helper->orderTypes();

        $this->status_orders_open = $helper->statusOrdersOpen();
        $this->filter_status_orders_open = $helper->filterStatusCase(1);
        $this->filter_status_orders_history = $helper->filterStatusCase(2);
    }
    public function list($request) {
        $size = Arr::get($request, 'size', Consts::DEFAULT_PER_PAGE) ?? Consts::DEFAULT_PER_PAGE;
        $currency = $request->currency;
        $coin = $request->coin;
        $trade_type = $request->trade_type;

        $list = Orderbook::query()
            ->when(
                $trade_type, function ($list) use($trade_type) { $list->where('trade_type', $trade_type); }
            )
            ->when(
                $currency, function ($list) use($currency) { $list->where('currency', $currency);}
                )
            ->when(
                $coin, function ($list) use($coin) { $list->where('coin', $coin);}
            )
            ->paginate($size)
            ->withQueryString();

        return $list;
    }
    public function params($request, $type = null) {
        $key = $request->params;
        if(!in_array($key, ['currency', 'coin', 'trade_type', 'type', 'status'])) return [];

        return match ($type) {
            1 => Schema::hasColumn('orderbooks', $key) ? Orderbook::query()->groupBy($key)->get($key)->pluck($key) : [],
            2 => self::params2($key),
            3 => self::params3($key),
            4 => self::params4($key),
            5,
            6 => self::transactionsParams($key),
            default => [],
        };

    }
    private function params2($key) {
        return match($key) {
            'status' => $this->filter_status_orders_history,
            'coin' => $this->coin,
            'currency' => $this->currency,
            'type' => $this->types_orders,
            'trade_type' => $this->trade_type,
            default => []
        };
    }
    private function params3($key) {
        return match($key) {
            'coin' => $this->coin,
            'currency' => $this->currency,
            default => []
        };
    }
    private function params4($key) {
        return match($key) {
          'status' => $this->filter_status_orders_open,
          'coin' => $this->coin,
          'currency' => $this->currency,
          'type' => $this->types_orders,
          default => []
        };
    }
    public function transactionsParams ($key) {
        if($key == 'type') return ['external', 'internal'];
        if($key == 'status') return Schema::hasColumn('transactions', $key) ? Transaction::query()
            ->groupBy($key)
            ->get($key)
            ->pluck($key) : [];
        return [];
    }
    public function ordersOpenList($request, $page = null) {
        $params = $request;
        $statuses = $this->status_orders_open;
        return Order::whereHas('user', function ($query) use($params) {
                $query->when($params['search_key'], function ($query) use ($params) {
                    $query->where('uid', 'like', '%' . $params['search_key'] . '%');
                });
            })
            ->select('*')
            ->selectRaw('price * quantity  as total,(executed_quantity/quantity) AS sort_quantity')
            ->when($params['user_id'], function ($query) use ($params) {
                return $query->where('user_id', $params['user_id']);
            })
            ->when(!empty($params['start_date']), function ($query) use ($params) {
                return $query->where('created_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                return $query->where('created_at', '<=', $params['end_date']);
            })
            ->when(!empty($params['coin']), function ($query) use ($params) {
                return $query->where('coin', $params['coin']);
            })
            ->when(!empty($params['currency']), function ($query) use ($params) {
                return $query->where('currency', $params['currency']);
            })
            ->when(!empty($params['trade_type']), function ($query) use ($params) {
                return $query->where('trade_type', $params['trade_type']);
            })
            ->when($params['type'], function ($query) use ($params) {
                $query->where('type', $params['type']);
            })
            ->when(isset($params['market_type']), function ($query) use ($params) {
                if ($params['market_type'] == Consts::ORDER_MARKET_TYPE_TYPE_CONVERT) {
                    return $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_CONVERT);
                }
            })
            ->when(!isset($params['market_type']), function ($query) use ($params) {
                return $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_NORMAL);
            })
            ->when($params['status'], function ($query) use($params) {
                return match($params['status']) {
                    'open' => $query->whereIn('status', [Consts::ORDER_STATUS_NEW, Consts::ORDER_STATUS_PENDING]),
                    'pending' => $query->where('status', Consts::ORDER_STATUS_STOPPING),
                    'partial_filled' => $query->where('status', Consts::ORDER_STATUS_EXECUTING),
                    default => $query->whereIn('status', $this->helper->statusOrdersOpen()),
                };
            }, function ($query) use($params, $statuses) {
                return $query->whereIn('status', $statuses);
            })
            ->when(!empty($params['sort']), function ($query) use ($params) {
                $query->orderBy($params['sort'] == 'executed_quantity' ? 'sort_quantity' : $params["sort"],
                    $params["sort_type"]);
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            })
            ->when(
                $page == -1,
                function ($query)use($params){return $query->get();},
                function ($query) use($params) {
                    return $query->paginate($params['size'] ?? Consts::DEFAULT_PER_PAGE)
                        ->withQueryString();
                });
    }
    public function ordersHistory($request, $page = null) {
        $params = $request;
        $orders = Order::with('user')
            ->when($params['search_key'], function ($query) use ($params) {
                $query->whereHas('user', function ($query) use($params) {
                    $query->where('uid', 'like', '%' . $params['search_key'] . '%');
                });
            })
            ->selectRaw(
                'orders.id as orderID,
                orders.user_id,
                orders.coin,
                orders.currency,
                orders.type,
                orders.trade_type,
                orders.executed_quantity,
                orders.stop_condition,
                orders.quantity,
                orders.price,
                orders.executed_price,
                orders.base_price,
                orders.fee,
                orders.updated_at as time,
                CASE
                    WHEN orders.executed_quantity = orders.quantity THEN "filled"
                    WHEN orders.status = "canceled" THEN "canceled"
                    WHEN orders.status = "executed" AND orders.executed_quantity <> orders.quantity THEN "filled"
                    ELSE "partial_filled"
                END as status,
                orders.status as status_order'
            )
        ->whereIn('status', $this->helper->statusOrdersHistory())
        ->when($params['start_date'], function ($query) use ($params) {
            $startDate = $params["start_date"];
            $endDate = $params["end_date"];
            $query->whereBetween('updated_at', array($startDate, $endDate));
        })
        ->when($request['user_id'], function ($query) use($request) {
            return $query->where('user_id', $request['id']);
        })
        ->when($params['coin'], function ($query) use ($params) {
            $query->where('coin', $params['coin']);
        })
        ->when($params['currency'], function ($query) use ($params) {
            $query->where('currency', $params['currency']);
        })
        ->when($params['type'], function ($query) use ($params) {
            $query->where('type', $params['type']);
        })
        ->when($params['trade_type'], function ($query) use ($params) {
            $query->where('trade_type', $params['trade_type']);
        })
        ->when($params['market_type'], function ($query) use ($params) {
            $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_CONVERT);
        })
        ->when(!$params['market_type'], function ($query) use ($params) {
            $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_NORMAL);
        })

        ->when($params['sort'], function ($query) use ($params) {
            if ($params['sort'] != 'total') {
                if ($params['sort'] == 'coin') {
                    $query->orderBy($params["sort"], $params["sort_type"]);
                    $query->orderBy('currency', $params["sort_type"]);
                } else {
                    if ($params['sort'] === 'status') {
                        $query->orderBy("custom_status", $params["sort_type"]);
                    } else {
                        $query->orderBy($params["sort"], $params["sort_type"]);
                    }
                }
            } else {
                $query->select(DB::raw('*,executed_price * executed_quantity as total'))
                    ->orderBy('total', $params['sort_type']);
            }
        }, function ($query) {
            $query->orderBy('updated_at', 'desc');
        })
        ->when(in_array($params['status'],  $this->helper->filterStatusCase(2)), function ($query)use($params) {
            if($params['status'] == 'filled') {
                $query->where(function ($q){
                    $q->where(function ($q) {
                        $q->where('status', 'executed')
                            ->where('executed_quantity', '=', DB::raw('quantity'));
                    })->orWhere(function ($query) {
                        $query->where('status', 'executed')
                            ->Where('executed_quantity', '<>', DB::raw('quantity'));
                    });
                });
            }
            elseif($params['status'] == 'canceled') $query->where('status', 'canceled');
            else {
                $query->where('executed_quantity','<>', DB::raw('quantity'))
                    ->where('status','<>', 'canceled');
            }
        })
        ->when(
            $page == -1,
            function ($query)use($params){return $query->get();},
            function ($query) use($params) {
                return $query->paginate($params['size'] ?? Consts::DEFAULT_PER_PAGE)
                    ->withQueryString();
            });

        if ($page != -1) $orders->getCollection();
        $orders->transform(function ($item) {
            $item['accountID'] = optional($item->user)->uid;
            return collect($item)->except(['user']);
        });

        return $orders;
    }
    public function ordersTradeHistory($request, $page = null) {
        $params = $request;
        $data = OrderTransaction::when($params['start_date'], function ($query) use ($params) {
            $query->whereBetween('created_at', array($params["start_date"], $params["end_date"]));
        })
        ->when($request['user_id'], function ($query) use($request) {
            $query->where(function ($query)use($request) {
                $query->where('seller_id', $request['user_id'])
                ->orWhere('buyer_id', $request['user_id']);
            });
        })
        ->when($params['coin'], function ($query) use ($params) {
            $query->where('coin', $params['coin']);
        })
        ->when($params['currency'], function ($query) use ($params) {
            $query->where('currency', $params['currency']);
        })
        ->when($params['search_key'], function ($query) use ($params) {
			/*$query->where(function ($query) use($params) {
				$query->where('buy_order_id', 'like', '%' . $params['search_key'] . '%')
					->orWhere('sell_order_id', 'like', '%' . $params['search_key'] . '%');
            });*/
			$query->where(function ($query)use($params) {
				$query->where('seller_id', $params['search_key'])
					->orWhere('buyer_id', $params['search_key']);
			});
        })
        ->orderbyDesc('created_at')
        ->when(
            $page == -1,
            function ($query){
                return $query->get();
            },
            function ($query) use($params) {
                return $query->paginate(Arr::get($params, 'size', Consts::DEFAULT_PER_PAGE))
                    ->withQueryString();
            });
        if($page != -1)  $data->getCollection();
        
        $data->map(function ($item) {
            $item['accountID'] = User::query()->where('id', '=', $item->buyer_id)->value('uid');
            return collect($item)->except(['buy_fee_amal','sell_fee_amal']);
        });
        return $data;
    }
    public function ordersOpen($request, $page = null) {
        $data = self::ordersOpenList($request, $page);
        if($page != -1) $data->getCollection();
        $data->transform(function ($item) {
            $_status = $item['status'];
            $status = match($_status) {
                Consts::ORDER_STATUS_NEW,
                Consts::ORDER_STATUS_PENDING => $this->helper->filterStatusCase(11),
                Consts::ORDER_STATUS_STOPPING => $this->helper->filterStatusCase(12),
                Consts::ORDER_STATUS_EXECUTING => $this->helper->filterStatusCase(13),
                default => $item['status']
            };
            $tmp = collect([
                'orderID' => $item['id'],
                'accountID' => optional($item->user)->uid,
                'time' => $item['updated_at'],
                'status' => $status,
                'status_order' => $item['status']
            ]);
            $fields = collect($item)->except(['id', 'user', 'updated_at', 'status']);
            return $tmp->merge($fields);
        });
        return $data;
    }
    public function transactions($request, $page = null) {
        $params = $request; //dd($params);
        $transactions = Transaction::with('user')
            ->when(!empty($params['user_id']), function ($query) use ($params) {
                return $query->where('user_id', $params['user_id']);
            })
            ->when(!empty($params['start_date']), function ($query) use ($params) {
                $startDate = Carbon::createFromTimestamp($params['start_date']);
                return $query->where('created_at', '>=', $startDate->timestamp);
            })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                $startDate = Carbon::createFromTimestamp($params['end_date']);
                return $query->where('created_at', '<', $startDate->timestamp);
            })
            ->when(!empty($params['search_key']), function ($query) use ($params) {
                $searchKey = Arr::get($params, 'search_key');
                return $query->where(function ($q) use ($searchKey) {
                    $q->where('id', 'like', '%' . $searchKey . '%');
                        /*->orWhere('transactions.tx_hash', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.user_id', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.blockchain_address', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.currency', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.status', 'like', '%' . $searchKey . '%')
                        ->orWhere('users.email', 'like', '%' . $searchKey . '%');*/
                });
            })
            ->when($params['currency'], function ($query) use ($params) {
                return $query->where('currency', $params['currency']);
            })
            ->when($params['collect'], function ($query) use ($params) {
                return $query->where('collect', $params['collect']);
            })
            ->when(!empty($params['trans_type']), function ($query) use ($params) {
                return match($params['trans_type']) {
                    Consts::TRANSACTION_TYPE_DEPOSIT => $query->filterDeposit(),
                    Consts::TRANSACTION_TYPE_WITHDRAW => $query->filterWithdraw(),
                    default => []
                };
            })
            ->when($params['status'], function ($query) use ($params) {
                return $query->where('status', $params['status']);
            })
            ->when($params['type'], function ($query) use ($params) {
                if($params['type'] == 'external') return $query->where('is_external', 1);
                return $query->where('status', $params['status']);
            })
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    return $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) use ($params) {
                    return $query->orderBy('created_at', 'desc');
                }
            )
            ->when($page == -1, function ($query) use($params) {
                return $query->get();
            }, function ($query)use($params) {
                return $query->paginate(Arr::get($params, 'size', Consts::DEFAULT_PER_PAGE))
                    ->withQueryString();
            });

        return $transactions;

    }
    public function withdraw($request, $page = null) {
        $result = self::transactions($request, $page);

        if($page != -1) $result->getCollection();
        $result->transform(function ($item) {
            $item_tmp = collect([
                'id' => $item['id'],
                'type' => ($item['is_external'] == 1) ? 'external' : '',
                'sender' => $item['user_id'],
                'amount' => BigNumber::of($item['amount'])->abs(),
                'currency' => $item['currency'],
                'receiver' => $item['to_address'],
                'hash' => $item['tx_hash'],
                'status' => $item['status'],
                'creation_time' => $item['created_at']
            ]);
            $additional_fields = collect($item)->except(['user', 'id', 'user_id', 'amount', 'currency', 'to_address', 'tx_hash', 'status', 'created_at']);
            return $item_tmp->merge($additional_fields);
        });
        return $result;
    }
    public function desposit($request, $page = null) {
        $result = self::transactions($request, $page);

        if($page != -1) $result->getCollection();
        $result->transform(function ($item) {
            $item_tmp = collect([
                'id' => $item['id'],
                'type' => ($item['is_external'] == 1) ? 'external' : '',
                'sender' => "-",
                'amount' => $item['amount'],
                'currency' => $item['currency'],
                'receiver' => $item['user_id'],
                'hash' => $item['tx_hash'],
                'status' => $item['status'],
                'creation_time' => $item['created_at']
            ]);
            $additional_fields = collect($item)->except(['user', 'id', 'user_id', 'amount', 'currency', 'to_address', 'tx_hash', 'status', 'created_at']);
            return $item_tmp->merge($additional_fields);
        });
        return $result;
    }

    public function cancelOrder($id): void
    {
        $order = Order::find($id);
        if ($order->canCancel()) {
			if (env("PROCESS_ORDER_REQUEST_REDIS", false)) {
				ProcessOrderRequestRedis::onNewOrderRequestCanceled([
					'orderId' => $order->id,
					'currency' => $order->currency,
					'coin' => $order->coin
				]);
			} else {
				ProcessOrderRequest::dispatch($order->id, ProcessOrderRequest::CANCEL);
			}
		}
        else throw new HttpException(422, '');
    }
}