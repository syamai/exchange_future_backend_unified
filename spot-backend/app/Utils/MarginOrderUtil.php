<?php
namespace App\Utils;

use Illuminate\Support\Facades\Auth;
use App\Service\Margin\Facades\InstrumentService;
use App\Service\Margin\Facades\MarginAccountService;
use App\Service\Margin\Facades\MarginCalculator;
use App\Service\Margin\Facades\IndexService;
use App\Models\User;
use App\Models\Position;
use App\Models\MarginAccount;
use App\Utils\BigNumber;
use App\Utils;
use App\Service\Margin\Utils as MarginUtils;
use App\Consts;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MarginOrderUtil
{
    public static function getOrderTypeText($order)
    {
        $locale = static::getOrderTypeKey($order);
        return __('margin.'.$locale);
    }

    public static function getOrderTypeKey($order)
    {
        if ($order->pair_type == 'ifd') {
            return 'ifd';
        } elseif ($order->pair_type == 'oco') {
            return 'oco';
        }
        if (!$order->stop_type) {
            if ($order->type == 'limit') {
                return 'limit';
            }
            return 'market';
        }
        switch ($order->stop_type) {
            case 'stop_limit':
                return 'stop_limit';
            case 'stop_market':
                return 'stop_market';
            case 'trailing_stop':
                return 'trailing_stop';
            case 'take_profit_market':
                return 'take_profit_market';
            case 'take_profit_limit':
                return 'take_profit_limit';
            case 'oco':
                return 'oco';
            case 'ifd':
                return 'ifd';
            default:
                return $order->type;
        }
    }

    public function getOrderData($request)
    {
        $order = $this->getCommonOrderData($request);
        $stopType = $request->input('stop_type', '');
        if (!$stopType) {
            $order = array_merge($order, $this->getPendingOrderData($request));
        } else {
            $order = array_merge($order, $this->getStoppingOrderData($request));
        }
        if ($order['is_reduce_only']) {
            $qty = $order['quantity'];
            $position = Position::getByCreator($order['account_id'])->getBySymbol($order['instrument_symbol'])->first();
            if ($position == null) {
                throw new HttpException(422, __('margin.order.error.reduce_only', [
                    'qty' => $qty,
                    'side' => $order['side'] == Consts::ORDER_SIDE_BUY ? __('trade_type.buy') : __('trade_type.sell'),
                    'symbol' => $order['instrument_symbol'],
                    'price' => $order['price'] ?? __('order.open_order.market'),
                    'size' => 0
                ]));
            }
            if (BigNumber::new($position->current_qty)->abs()->comp($order['quantity']) < 0) {
                $order['quantity'] = BigNumber::new($position->current_qty)->abs()->toString();
                $order['remaining'] = BigNumber::new($position->current_qty)->abs()->toString();
            }
            $side = BigNumber::new($position->current_qty)->comp(0) > 0 ? Consts::ORDER_SIDE_BUY : Consts::ORDER_SIDE_SELL;
            if (BigNumber::new($order['quantity'])->comp(0) == 0 || $side == $order['side']) {
                throw new HttpException(422, __('margin.order.error.reduce_only', [
                    'qty' => $qty,
                    'side' => $order['side'] == Consts::ORDER_SIDE_BUY ? __('trade_type.buy') : __('trade_type.sell'),
                    'symbol' => $order['instrument_symbol'],
                    'price' => $order['price'] ?? __('order.open_order.market'),
                    'size' => $position->current_qty
                ]));
            }
        }
        return $order;
    }
    private function getMarginOrMamAccount($request)
    {
        if ($request->input('is_mam', 0)) {
            return MarginAccount::where('manager_id', auth()->id())->where('is_mam', 1)->first();
        }
        return MarginAccount::where('owner_id', auth()->id())->first();
    }

    private function getCommonOrderData($request)
    {
        $account = $this->getMarginOrMamAccount($request);
        if (!$account) {
            throw new HttpException(422, __('margin.order.error.permission_not_alow'));
        }
        return [
            'account_id' => $account->id,
            'instrument_symbol' => $request->input('instrument_symbol', ''),
            'owner_email' => @User::find($account->owner_id)->email ?? null,
            'manager_email' => @User::find($account->manager_id)->email ?? null,
            'side' => $request->input('side', ''),
            'type' => $request->input('type'),
            'stop_type' => $request->input('stop_type', null),
            'quantity' => $request->input('quantity', null),
            'remaining' => $request->input('quantity', null),
            'price' => null,
            'stop_price' => null,
            'trigger_price' => null,
            'stop_condition' => null,
            'trigger' => null,
            'trail_value' => null,
            'is_post_only' => false,
            'is_hidden' => false,
            'display_quantity' => 0,
            'is_reduce_only' => false,
            'time_in_force' => null,
            'status' => null,
            'note' => null,
            'created_at' => Utils::currentMilliseconds(),
            'updated_at' => Utils::currentMilliseconds(),
        ];
    }
    private function getPendingOrderData($request)
    {
        $type = $request->input('type', '');
        $order = [];
        switch ($type) {
            case Consts::ORDER_TYPE_LIMIT:
                $order['price'] = $request->input('price', null);
                $order['is_hidden'] = $request->input('is_hidden', false);
                $order['display_quantity'] = $request->input('display_quantity', 0);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = $request->input('time_in_force', null);
                if ($request->input('time_in_force', null) == Consts::ORDER_TIME_IN_FORCE_GTC) {
                    $order['is_post_only'] = $request->input('is_post_only', false);
                }
                break;
            case Consts::ORDER_TYPE_MARKET:
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = Consts::ORDER_TIME_IN_FORCE_IOC;
                break;
        }
        $order['status'] = Consts::ORDER_STATUS_NEW;
        return $order;
    }

    private function getTriggerPrice($symbol, $trigger)
    {
        $instrument = InstrumentService::get($symbol);
        switch ($trigger) {
            case Consts::ORDER_STOP_TRIGGER_LAST:
                return $instrument['extra']['last_price'] ?? 0;
            case Consts::ORDER_STOP_TRIGGER_MARK:
                return $instrument['extra']['mark_price'] ?? 0;
            case Consts::ORDER_STOP_TRIGGER_INDEX:
                return MarginUtils::getIndexPrice($instrument);
            default:
                throw new HttpException(422, "Unknown trigger ({$trigger})");
        }
    }

    private function getStoppingOrderData($request)
    {
        $stopType = $request->input('stop_type', '');
        $order = [];
        switch ($stopType) {
            case Consts::ORDER_STOP_TYPE_LIMIT:
                $order['price'] = $request->input('price', null);
                $order['stop_price'] = $request->input('stop_price', null);
                $order['trigger'] = $request->input('trigger', null);
                $order['is_hidden'] = $request->input('is_hidden', false);
                $order['display_quantity'] = $request->input('display_quantity', 0);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = $request->input('time_in_force', null);
                if ($request->input('time_in_force', null) == Consts::ORDER_TIME_IN_FORCE_GTC) {
                    $order['is_post_only'] = $request->input('is_post_only', false);
                }
                break;
            case Consts::ORDER_STOP_TYPE_MARKET:
                $order['stop_price'] = $request->input('stop_price', null);
                $order['trigger'] = $request->input('trigger', null);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = Consts::ORDER_TIME_IN_FORCE_IOC;
                break;
            case Consts::ORDER_STOP_TYPE_TRAILING_STOP:
                $order['trail_value'] = $request->input('trail_value', null);
                $order['trigger'] = $request->input('trigger', null);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = Consts::ORDER_TIME_IN_FORCE_IOC;
                $order['vertex_price'] = $this->getTriggerPrice($request->input('instrument_symbol', null), $order['trigger']);
                $order['stop_price'] = BigNumber::new($order['vertex_price'])->add($order['trail_value'])->toString();
                break;
            case Consts::ORDER_STOP_TYPE_TAKE_PROFIT_MARKET:
                $order['stop_price'] = $request->input('stop_price', null);
                $order['trigger'] = $request->input('trigger', null);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = Consts::ORDER_TIME_IN_FORCE_IOC;
                break;
            case Consts::ORDER_STOP_TYPE_TAKE_PROFIT_LIMIT:
                $order['price'] = $request->input('price', null);
                $order['stop_price'] = $request->input('stop_price', null);
                $order['trigger'] = $request->input('trigger', null);
                $order['is_hidden'] = $request->input('is_hidden', false);
                $order['display_quantity'] = $request->input('display_quantity', 0);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = $request->input('time_in_force', null);
                if ($request->input('time_in_force', null) == Consts::ORDER_TIME_IN_FORCE_GTC) {
                    $order['is_post_only'] = $request->input('is_post_only', false);
                }
                break;
            case Consts::ORDER_STOP_TYPE_OCO:
                $order['price'] = $request->input('price', null);
                $order['stop_price'] = $request->input('stop_price', null);
                $order['trigger_price'] = $request->input('trigger_price', null);
                $order['trigger'] = $request->input('trigger', null);
                $order['is_hidden'] = $request->input('is_hidden', false);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = $request->input('time_in_force', null);
                if ($request->input('time_in_force', null) == Consts::ORDER_TIME_IN_FORCE_GTC) {
                    $order['is_post_only'] = $request->input('is_post_only', false);
                }
                $order['pair_type'] = Consts::ORDER_PAIR_TYPE_OCO;
                break;
            case Consts::ORDER_STOP_TYPE_IFD:
                $order['price'] = $request->input('price', null);
                $order['stop_price'] = $request->input('stop_price', null);
                $order['trigger'] = $request->input('trigger', null);
                $order['is_hidden'] = $request->input('is_hidden', false);
                $order['is_reduce_only'] = $request->input('is_reduce_only', false);
                $order['time_in_force'] = Consts::ORDER_TIME_IN_FORCE_GTC;
                if ($request->input('time_in_force', null) == Consts::ORDER_TIME_IN_FORCE_GTC) {
                    $order['is_post_only'] = $request->input('is_post_only', false);
                }
                $order['pair_type'] = Consts::ORDER_PAIR_TYPE_IFD;
                break;
        }
        $order['trigger'] = strtolower($order['trigger']);
        $order['status'] = Consts::ORDER_STATUS_NEW;
        if ($request->input('stop_type', null) == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_LIMIT || $request->input('stop_type', null) == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_MARKET) {
            if ($request->input('side', null) == Consts::ORDER_SIDE_BUY) {
                $order['stop_condition'] = Consts::ORDER_STOP_CONDITION_LE;
            } else {
                $order['stop_condition'] = Consts::ORDER_STOP_CONDITION_GE;
            }
        } else {
            if ($request->input('side', null) == Consts::ORDER_SIDE_BUY) {
                $order['stop_condition'] = Consts::ORDER_STOP_CONDITION_GE;
            } else {
                $order['stop_condition'] = Consts::ORDER_STOP_CONDITION_LE;
            }
        }
        return $order;
    }
    public function validateOrderInput($order)
    {
        // Validate
        $instrument = InstrumentService::get($order['instrument_symbol']);
        if ($instrument->state == Consts::INSTRUMENT_STATE_CLOSE && $order['note'] != Consts::MARGIN_ORDER_NOTE_SETTLEMENT) {
            throw new HttpException(422, __('margin.order.error.instrument.expired', ['name' => $instrument->symbol]));
        }
        if ($order['quantity'] == 0) {
            throw new HttpException(422, __('margin.order.error.quantity.required'));
        }
        if ($order['quantity'] < 0) {
            throw new HttpException(422, __('margin.order.error.quantity.greater_than_zero'));
        }
        if (BigNumber::new($order['quantity'])->comp($instrument->max_order_qty) > 0) {
            throw new HttpException(422, __('margin.order.error.quantity.too.high'));
        }
        if ($order['type'] == Consts::ORDER_TYPE_LIMIT) {
            if (empty($order['price']) && BigNumber::new($order['price'])->comp(0) <= 0) {
                // Price is null
                throw new HttpException(422, __('margin.order.error.price.required'));
            }
            if (!BigNumber::new($order['price'])->isModulusFor($instrument->tick_size)) {
                throw new HttpException(422, __('margin.order.error.price.not.valid'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_LIMIT || $order['stop_type'] == Consts::ORDER_STOP_TYPE_MARKET || $order['stop_type'] == Consts::ORDER_STOP_TYPE_IFD) {
            if (empty($order['stop_price']) && BigNumber::new($order['stop_price'])->comp(0) <= 0) {
                // Stop price is null
                throw new HttpException(422, __('margin.order.error.stop_price.required'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_TRAILING_STOP) {
            if (empty($order['trail_value']) && BigNumber::new($order['trail_value'])->comp(0) == 0) {
                // Trail value is null
                throw new HttpException(422, __('margin.order.error.trail_value.required'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_MARKET || $order['stop_type'] == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_LIMIT) {
            if (empty($order['stop_price']) && BigNumber::new($order['stop_price'])->comp(0) <= 0) {
                // Trigger price is null
                throw new HttpException(422, __('margin.order.error.trigger_price.required'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_OCO) {
            if (empty($order['stop_price']) && BigNumber::new($order['stop_price'])->comp(0) <= 0) {
                // Stop price is null
                throw new HttpException(422, __('margin.order.error.stop_price.required'));
            }
            if (empty($order['trigger_price']) && BigNumber::new($order['trigger_price'])->comp(0) <= 0) {
                // Trigger price is null
                throw new HttpException(422, __('margin.order.error.trigger_price.required'));
            }
        }
        if (!empty($order['price']) && $order['price'] && BigNumber::new($order['price'])->comp($instrument->max_price) > 0) {
            throw new HttpException(422, __('margin.order.error.price.too.high'));
        }
        if (!empty($order['trigger_price']) && $order['trigger_price'] && BigNumber::new($order['trigger_price'])->comp($instrument->max_price) > 0) {
            throw new HttpException(422, __('margin.order.error.price.too.high'));
        }
        if (!empty($order['stop_price']) && $order['stop_price'] && BigNumber::new($order['stop_price'])->comp($instrument->max_price) > 0) {
            throw new HttpException(422, __('margin.order.error.price.too.high'));
        }
        if (!empty($order['trail_value']) && $order['trail_value'] && BigNumber::new($order['trail_value'])->comp($instrument->max_price) > 0) {
            throw new HttpException(422, __('margin.order.error.price.too.high'));
        }

        // Validate for buy
        if ($order['side'] == Consts::ORDER_SIDE_BUY) {
            $this->validateBuyOrder($order);
        }
        // Validate for sell
        if ($order['side'] == Consts::ORDER_SIDE_SELL) {
            $this->validateSellOrder($order);
        }
    }
    private function validateBuyOrder($order)
    {
        // Get instrument info
        $instrument = InstrumentService::get($order['instrument_symbol']);
        // Get Compare price
        $comparePrice = $instrument['extra']['last_price'];
        $order['stop_price'] = $order['stop_price'] ?? $comparePrice;
        if (!isset($order['trigger'])) {
            $order['trigger'] = '';
        }
        if ($order['trigger'] == Consts::ORDER_STOP_TRIGGER_MARK) {
            $comparePrice = $instrument['extra']['mark_price'];
        } elseif ($order['trigger'] == Consts::ORDER_STOP_TRIGGER_INDEX) {
            $lastIndex = IndexService::getLastIndex($instrument['reference_index']);
            if ($lastIndex == null) {
                throw new HttpException(422, __('margin.order.error.index.not_exist'));
            }
            $comparePrice = $lastIndex->value;
        } elseif ($order['trigger'] == Consts::ORDER_STOP_TRIGGER_LAST || $order['trigger'] == null || $order['trigger'] == '') {
        } else {
            throw new HttpException(422, __('margin.order.error.trigger.not_valid'));
        }

        if (empty($comparePrice)) {
            $comparePrice = 0;
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_LIMIT || $order['stop_type'] == Consts::ORDER_STOP_TYPE_MARKET) {
            if (BigNumber::new($order['stop_price'])->comp($comparePrice) <= 0) {
                // Compare price >= stop price
                throw new HttpException(422, __('margin.order.error.stop_price.lt_market_price'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_TRAILING_STOP) {
            if (BigNumber::new($order['trail_value'])->isNegative()) {
                // Trail value is negative
                throw new HttpException(422, __('margin.order.error.trail_value.negative'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_MARKET || $order['stop_type'] == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_LIMIT) {
            if (BigNumber::new($order['stop_price'])->comp($comparePrice) >= 0) {
                // Compare price >= trigger price
                throw new HttpException(422, __('margin.order.error.trigger_price.gt_market_price'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_OCO) {
            if (BigNumber::new($order['price'])->comp($comparePrice) >= 0) {
                throw new HttpException(422, __('margin.order.error.limit.gt_market_price'));
            }
            if (BigNumber::new($order['stop_price'])->comp($order['price']) <= 0) {
                throw new HttpException(422, __('margin.order.error.price.invalid'));
            }
            if (BigNumber::new($order['stop_price'])->comp($comparePrice) <= 0) {
                throw new HttpException(422, __('margin.order.error.price.invalid'));
            }
        }
    }
    private function validateSellOrder($order)
    {
        // Get instrument info
        $instrument = InstrumentService::get($order['instrument_symbol']);
        // Get Compare price
        $comparePrice = $instrument['extra']['last_price'];
        $order['trigger'] = $order['trigger'] ?? $comparePrice;
        if ($order['trigger'] == Consts::ORDER_STOP_TRIGGER_MARK) {
            $comparePrice = $instrument['extra']['mark_price'];
        } elseif ($order['trigger'] == Consts::ORDER_STOP_TRIGGER_INDEX) {
            $lastIndex = IndexService::getLastIndex($instrument['reference_index']);
            if ($lastIndex == null) {
                throw new HttpException(422, __('margin.order.error.index.not_exist'));
            }
            $comparePrice = $lastIndex->value;
        }

        if (empty($comparePrice)) {
            $comparePrice = 0;
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_LIMIT || $order['stop_type'] == Consts::ORDER_STOP_TYPE_MARKET) {
            if (BigNumber::new($order['stop_price'])->comp($comparePrice) >= 0) {
                // Compare price >= stop price
                throw new HttpException(422, __('margin.order.error.stop_price.gt_market_price'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_TRAILING_STOP) {
            if (!BigNumber::new($order['trail_value'])->isNegative()) {
                // Trail value is negative
                throw new HttpException(422, __('margin.order.error.trail_value.not_negative'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_MARKET || $order['stop_type'] == Consts::ORDER_STOP_TYPE_TAKE_PROFIT_LIMIT) {
            if (BigNumber::new($order['stop_price'])->comp($comparePrice) <= 0) {
                // Compare price <= trigger price
                throw new HttpException(422, __('margin.order.error.trigger_price.lt_market_price'));
            }
        }
        if ($order['stop_type'] == Consts::ORDER_STOP_TYPE_OCO) {
            if (BigNumber::new($order['price'])->comp($comparePrice) <= 0) {
                throw new HttpException(422, __('margin.order.error.limit.lt_market_price'));
            }
            if (BigNumber::new($order['stop_price'])->comp($order['price']) >= 0) {
                throw new HttpException(422, __('margin.order.error.price.invalid'));
            }
            if (BigNumber::new($order['stop_price'])->comp($comparePrice) >= 0) {
                throw new HttpException(422, __('margin.order.error.price.invalid'));
            }
        }
    }
    public function validateBalance($order)
    {
        $instrument = InstrumentService::get($order['instrument_symbol']);
        $account = MarginAccount::find($order['account_id']);
        $position = Position::firstOrCreate(
            [
                'account_id' => $order['account_id'],
                'symbol' => $order['instrument_symbol']
            ],
            [
                'leverage' => isset($order['leverage']) ? $order['leverage'] : 0,
                'is_cross' => isset($order['leverage']) ? 0 : 1,
                'required_init_margin_percent' => $instrument->init_margin,
                'required_maint_margin_percent' => $instrument->maint_margin,
                'multiplier' => $instrument->multiplier,
                'open_order_buy_qty' => 0,
                'open_order_sell_qty' => 0,
                'open_order_buy_value' => 0,
                'open_order_sell_value' => 0,
                'risk_limit' => $instrument->risk_limit ? $instrument->risk_limit : 0,
                'owner_email' => @User::find($account->owner_id)->email ?? null,
                'manager_email' => @User::find($account->manager_id)->email ?? null,
            ]
        );
        $account = MarginAccountService::getAndLockAccount($order['account_id']);
        $price = $instrument['extra']['last_price'];
        if (empty($price)) {
            $price = 0;
        }
        if ($order['type'] == 'limit' || $order['type'] == 'stop_limit' || $order['type'] == 'take_profit_limit') {
            $price = $order['price'];
        } elseif ($order['type'] == 'stop_market') {
            $price = $order['stop_price'];
        } elseif ($order['type'] == 'trailing_stop') {
            $price = BigNumber::new($price)->add($order['trail_value'])->toString();
        } elseif ($order['type'] == 'take_profit_market') {
            $price = $order['stop_price'];
        }
        $opening = MarginCalculator::calculateOpenMargin($price, $order['quantity'], $position, $account);
        // Cost <= available balance
        if (BigNumber::new($opening['margin'])->comp($account->available_balance) <= 0) {
            return true;
        }
        return false;
    }
}
