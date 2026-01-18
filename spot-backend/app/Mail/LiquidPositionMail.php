<?php
namespace App\Mail;

use App\Service\Margin\Facades\IndexService;
use App\Service\Margin\Facades\InstrumentService;
use App\Service\Margin\MarginBigNumber;
use App\Http\Services\UserService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LiquidPositionMail extends Mailable
{
    use Queueable, SerializesModels;
     /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $user;
    protected $data;
    protected $userService;

    public function __construct($user, $data)
    {
        $this->user = $user;
        $this->data = $data;
    }
     /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $instrument = InstrumentService::get($this->data->symbol, true);

        $date = (string)Carbon::now('UTC');
        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($this->user->id);
        $title = __('emails.received_liquid_position.subject', ['symbol' => $this->data->symbol], $locale);
        $subject = $title . ' - [' . $date . '] (UTC)' ;
         return  $this->view('emails.email_received_liquid_position')
            ->subject($subject)
            ->to($this->user->email)
            ->with([
                'locale' => $locale,
                'email' => $this->user->email,
                'symbol' => $this->data->symbol,
                'current_qty' => $this->data->current_qty,
                'mark_price' => MarginBigNumber::round($instrument->extra->mark_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'liquidation_price' => MarginBigNumber::round($this->data->liquidation_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'side' => $this->data->current_qty > 0 ? __('trade_type.buy', [], $locale) : __('trade_type.sell', [], $locale),
                'leverage' => MarginBigNumber::round($this->data->leverage, MarginBigNumber::ROUND_MODE_HALF_UP, 2),
                'maint_margin' => MarginBigNumber::round($this->data->maint_margin, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'bankrupt_price' => MarginBigNumber::round($this->data->bankrupt_price, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'date' => $date . ' UTC',
                'index_price' => MarginBigNumber::round(@IndexService::getLastIndex($instrument->reference_index)->value ?? 0, MarginBigNumber::ROUND_MODE_HALF_UP, 8),
                'user' => $this->user
            ]);
    }
}
