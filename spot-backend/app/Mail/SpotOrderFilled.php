<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\UserAntiPhishing;
use App\Utils\BigNumber;
use Carbon\Carbon;
use http\Client\Curl\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpotOrderFilled extends Mailable
{
    use Queueable, SerializesModels;

    private $user;
    private $order;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $order)
    {
		$this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->user = $user;
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $userService = new UserService();
        $type = 'full';
		if (BigNumber::new($this->order->executed_quantity)->comp($this->order->quantity) < 0) {
			$type = 'partial';
		}

		$tradeTypeName = match($this->order->type) {
			Consts::ORDER_TYPE_LIMIT => 'Limit',
			Consts::ORDER_TYPE_MARKET => 'Market',
			Consts::ORDER_TYPE_STOP_LIMIT => 'Stop limit',
			Consts::ORDER_TYPE_STOP_MARKET => 'Stop market',
		};


        $locale = $userService->getUserLocale($this->user->id);
        $title = $type == 'full' ? __('emails.spot_order_trade_full.title_create', ['APP_NAME' => config('app.name'), 'trade_name' => $tradeTypeName], $locale) : __('emails.spot_order_trade_partial.title_create', ['APP_NAME' => config('app.name'), 'trade_name' => $tradeTypeName], $locale);
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $this->user->id,
        ])->first();

        $currency = strtoupper($this->order->currency);
        $coin = strtoupper($this->order->coin);
        $remainingQuantity = BigNumber::new($this->order->quantity)->sub($this->order->executed_quantity)->toString();


        return  $this->view('emails.spot_order_trade_'.$type)
            ->subject($title)
            ->to($this->user->email)
            ->with([
                'email' => $this->user->email,
                'order'  => $this->order,
                'locale' => $locale,
				'date' => date('Y-m-d H:i:s', $this->order->updated_at / 1000),
                'trade_name' => $tradeTypeName,
                'order_type' => ucfirst($this->order->trade_type),
                'pair' => "{$coin}/{$currency}",
                'currency' => $currency,
                'coin' => $coin,
                'price' => BigNumber::new($this->order->executed_price)->toString(),
				'quantity' => BigNumber::new($this->order->executed_quantity)->toString(),
				'remaining_quantity' => $remainingQuantity,
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
