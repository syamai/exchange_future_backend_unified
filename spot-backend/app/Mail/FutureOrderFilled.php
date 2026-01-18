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

class FutureOrderFilled extends Mailable
{
    use Queueable, SerializesModels;

    private $user;
    private $data;

	/**
	 * Create a new message instance.
	 *
	 * @param $user
	 * @param $data
	 */
    public function __construct($user, $data)
    {
		$this->queue = Consts::QUEUE_FUTURE_MAIL;
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
        $userService = new UserService();
		$locale = $userService->getUserLocale($this->user->id);

		$filledType = strtolower($this->data['filledType']);
		$orderType = ucfirst(strtolower($this->data['orderType']));
		$symbol = strtolower($this->data['symbol']);
		$symbols = explode('/', $symbol);
		$side = strtolower($this->data['side']);

        $currency = strtoupper(isset($symbols[1]) ? $symbols[1] : '');
        $coin = strtoupper($symbols[0]);
        $quantity = isset($this->data['filledQuantity']) ? $this->data['filledQuantity'] : $this->data['quantity'];
        $remainingQuantity = isset($this->data['remaingQuantity']) ? $this->data['remaingQuantity'] : 0;
        $price = $this->data['price'];
        $template = $filledType == 'filled' ? 'emails.future_order_trade_full' : 'emails.future_order_trade_partial';
		$title = $filledType == 'filled' ? __('emails.future_order_trade_full.title_create', ['APP_NAME' => config('app.name'), 'order_type' => $orderType], $locale) : __('emails.future_order_trade_partial.title_create', ['APP_NAME' => config('app.name'), 'order_type' => $orderType], $locale);
		$antiPhishingCode = UserAntiPhishing::where([
			'is_active' => true,
			'user_id' => $this->user->id,
		])->first();



        return  $this->view($template)
            ->subject($title)
            ->to($this->user->email)
            ->with([
                'email' => $this->user->email,
                'locale' => $locale,
				'date' => date('Y-m-d H:i:s', strtotime($this->data['time'])),
                'order_type' => $orderType,
                'side' => ucfirst($side),
                'symbol' => strtoupper($symbol),
                'currency' => $currency,
                'coin' => $coin,
                'price' => BigNumber::new($price)->toString(),
				'quantity' => BigNumber::new($quantity)->toString(),
				'remaining_quantity' => BigNumber::new($remainingQuantity)->toString(),
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
