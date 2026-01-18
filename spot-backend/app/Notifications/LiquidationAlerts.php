<?php

namespace App\Notifications;

use App\Service\Margin\Facades\IndexService;
use App\Service\Margin\Facades\InstrumentService;
use App\Service\Margin\MarginBigNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Session;
use App\Consts;
use Carbon\Carbon;

class LiquidationAlerts extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $data;

    public function __construct($user, $data)
    {
        $this->user = $user;
        try {
            $jsonData = json_decode($data);
        } catch (\Exception $e) {
            $jsonData = $data;
        }
        $this->data = $jsonData;
    }

    public function toMail($notifiable)
    {
        return new \App\Mail\LiquidPositionMail($this->user, $this->data);
    }

    public function toLine($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    public function toTelegram($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable)
    {
        $instrument = InstrumentService::get($this->data->symbol, true);
        $date = (string)Carbon::now('UTC');
        $locale = Session::get('user.locale', Consts::DEFAULT_USER_LOCALE);

        return __('emails.received_verify_document.dear_account', [], $locale)
            . " " . $this->user->email

            . "\n" . __('emails.liquid_position_line_1', [
                'symbol' => $this->data->symbol,
                'current_qty' => $this->data->current_qty
            ], $locale)

            . "\n" . __('emails.liquid_position_line_2', [
                'symbol' => $this->data->symbol,
                'mark_price' => MarginBigNumber::round($instrument->extra->mark_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'liquidation_price' => MarginBigNumber::round($this->data->liquidation_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8)
            ], $locale)


            . "\n" . __('emails.liquid_position_line_3', ['symbol' => $this->data->symbol], $locale)

            . "\n" . __('emails.liquid_position.side', [], $locale)
            . ": " . ($this->data->current_qty > 0 ? __('trade_type.buy', [], $locale) : __('trade_type.sell', [], $locale))

            . "\n" . __('emails.liquid_position.qty', [], $locale)
            . ": " . $this->data->current_qty

            . "\n" . __('emails.liquid_position.lev', [], $locale)
            . ": " . MarginBigNumber::round($this->data->leverage, MarginBigNumber::ROUND_MODE_HALF_UP, 2)

            . "\n" . __('emails.liquid_position.mark_price', [], $locale)
            . ": " . MarginBigNumber::round($instrument->extra->mark_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8)

            . "\n" . __('emails.liquid_position.liq_price', [], $locale)
            . ": " . MarginBigNumber::round($this->data->liquidation_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8)

            . "\n" . __('emails.liquid_position_line_4', [
                'symbol' => $this->data->symbol,
                'current_qty' => $this->data->current_qty,
                'leverage' => MarginBigNumber::round($this->data->leverage, MarginBigNumber::ROUND_MODE_HALF_UP, 2)
            ], $locale)

            . "\n" . __('emails.liquid_position_line_5', [
                'maint_margin' => MarginBigNumber::round($this->data->maint_margin, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'liquidation_price' => MarginBigNumber::round($this->data->liquidation_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'bankrupt_price' => MarginBigNumber::round($this->data->bankrupt_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8)
            ], $locale)

            . "\n" . __('emails.liquid_position_line_6', [
                'date' => $date . ' UTC',
                'symbol' => $this->data->symbol,
                'index_price' => MarginBigNumber::round(@IndexService::getLastIndex($instrument->reference_index)->value ?? 0, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'mark_price' => MarginBigNumber::round($instrument->extra->mark_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8)
            ], $locale);
    }
}
