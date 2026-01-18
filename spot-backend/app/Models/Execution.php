<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Execution extends Model
{
    public $table = 'executions';

    public $fillable = [
        'execID',
        'orderID',
        'clOrdID',
        'clOrdLinkID',
        'account',
        'symbol',
        'side',
        'lastQty',
        'lastPx',
        'underlyingLastPx',
        'lastMkt',
        'lastLiquidityInd',
        'simpleOrderQty',
        'orderQty',
        'price',
        'displayQty',
        'stopPx',
        'pegOffsetValue',
        'pegPriceType',
        'currency',
        'settlCurrency',
        'execType',
        'ordType',
        'timeInForce',
        'execInst',
        'contingencyType',
        'exDestination',
        'ordStatus',
        'triggered',
        'workingIndicator',
        'ordRejReason',
        'simpleLeavesQty',
        'leavesQty',
        'simpleCumQty',
        'cumQty',
        'avgPx',
        'commission',
        'tradePublishIndicator',
        'multiLegReportingType',
        'text',
        'trdMatchID',
        'execCost',
        'execComm',
        'homeNotional',
        'foreignNotional',
        'transactTime',
        'timestamps',
    ];
}
