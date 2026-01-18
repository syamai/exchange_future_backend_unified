<?php


namespace App\Models;

use App\Consts;
use Illuminate\Database\Eloquent\Model;

class EntryBalanceHistory extends Model
{
    protected $table = 'entry_balance_history';

    protected $fillable = ['account_id', 'symbol', 'realised_pnl', 'close_margin',
        'balance', 'buy_order_id', 'sell_order_id', 'quantity', 'available_balance', 'contest_id', 'amount'];

    public static function quickSave($account, $symbol, $realisePnl, $closeMargin, $trade, $quantity)
    {
        $contest = MarginContest::where('status', Consts::MARGIN_STARTED)->first();
        if (!$contest) {
            return;
        }
        $entry = MarginEntry::where('contest_id', $contest->id)
            ->where('account_id', $account->id)
            ->where('status', Consts::ENTRY_JOINED)
            ->first();
        if (!$entry) {
            return;
        }
        return self::create([
            'account_id' => $account->id,
            'symbol' => $symbol,
            'realised_pnl' => $realisePnl,
            'close_margin' => $closeMargin,
            'balance' => $account->balance,
            'buy_order_id' => $trade ? $trade->buy_order_id : null,
            'sell_order_id' => $trade ? $trade->sell_order_id : null,
            'amount' => $trade ? $trade->amount : 0,
            'quantity' => $quantity,
            'available_balance' => $account->available_balance,
            'contest_id' => $contest->id,
        ]);
    }
}
